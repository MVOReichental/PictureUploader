<?php
namespace de\mvo\pictureuploader;

use de\mvo\pictureuploader\image\Resizer;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class QueueItem
{
    /**
     * @var Date
     */
    public $date;
    /**
     * @var string
     */
    public $folder;
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $title;

    public static function fromFile($filename)
    {
        $file = sprintf("%s/%s", Config::getValue(null, "queue"), basename($filename));

        if (!file_exists($file)) {
            return null;
        }

        return unserialize(file_get_contents($file));
    }

    public function save()
    {
        $data = serialize($this);

        $queuePath = Config::getValue(null, "queue");

        if (!is_dir($queuePath)) {
            mkdir($queuePath, 0775, true);
        }

        file_put_contents(sprintf("%s/%s.serialize", $queuePath, md5($data)), $data);
    }

    public function process()
    {
        $cachePath = sprintf("%s/%d/%s", Config::getValue(null, "pictures-cache"), $this->date->format("Y"), $this->name);

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0775, true);
        }

        list($largeWidth, $largeHeight) = explode("x", Config::getValue(null, "large-size", "1500x1000"));
        list($smallWidth, $smallHeight) = explode("x", Config::getValue(null, "small-size", "600x200"));

        $filesystem = new Filesystem;

        $finder = new Finder;

        $finder->files();
        $finder->in(sprintf("%s/%d/%s", Config::getValue(null, "source"), $this->date->format("Y"), $this->folder));
        $finder->depth("==0");
        $finder->name("/\.jpg/i");

        $validFiles = array();

        foreach ($finder as $item) {
            $originalFile = $item->getPathname();

            $md5 = md5_file($originalFile);

            $largeFile = sprintf("%s/%s_large.jpg", $cachePath, $md5);
            $smallFile = sprintf("%s/%s_small.jpg", $cachePath, $md5);

            $validFiles[] = $largeFile;
            $validFiles[] = $smallFile;

            $resizer = new Resizer($originalFile);

            if (!is_file($largeFile)) {
                Logger::log("Saving large version of " . $originalFile);
                if (!imagejpeg($resizer->resize($largeWidth, $largeHeight), $largeFile)) {
                    throw new RuntimeException(sprintf("Unable to save picture to %s", $largeFile));
                }
            }

            if (!is_file($smallFile)) {
                Logger::log("Saving small version of " . $originalFile);
                if (!imagejpeg($resizer->resize($smallWidth, $smallHeight), $smallFile)) {
                    throw new RuntimeException(sprintf("Unable to save picture to %s", $largeFile));
                }
            }
        }

        $finder = new Finder;

        $finder->in($cachePath);
        $finder->notName("album.json");

        // Cleanup old files
        foreach ($finder as $item) {
            if (in_array($item->getFilename(), $validFiles)) {
                continue;
            }

            $filesystem->remove($item->getPathname());
        }

        $remoteAppDir = Config::getValue(null, "remote-app-dir");
        $logFile = Config::getValue(null, "rsync-log");
        $remotePath = sprintf("%s/httpdocs/pictures/%s/%s", $remoteAppDir, $this->date->format("Y"), $this->name);
        $sshKey = Config::getValue(null, "ssh-key");
        $sshUser = Config::getValue(null, "ssh-user");
        $host = Config::getValue(null, "host");
        $updateScript = sprintf("%s/bin/update-pictures.php", $remoteAppDir);

        $rsyncCommand = array
        (
            "rsync",
            "-avz",
            "--delete",
            sprintf("--log-file %s", escapeshellarg($logFile)),
            sprintf("--rsync-path %s", escapeshellarg(sprintf("mkdir -p %s && rsync", $remotePath))),
            sprintf("-e %s", escapeshellarg(sprintf("ssh -i %s", $sshKey))),
            escapeshellarg($cachePath),
            escapeshellarg(sprintf("%s@%s:%s", $sshUser, $host, $remotePath))
        );

        $process = new Process(implode(" ", $rsyncCommand));
        $process->mustRun();

        $process = new Process(sprintf("ssh -i %s %s %s", escapeshellarg($sshKey), escapeshellarg($sshUser . "@" . $host), escapeshellarg($updateScript)));
        $process->mustRun();
    }
}