<?php
namespace de\mvo\pictureuploader;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class Album
{
    /**
     * @var string
     */
    public $year;
    /**
     * @var string
     */
    public $folder;
    /**
     * @var Date
     */
    public $date;
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $title;
    /**
     * @var string
     */
    public $text;
    /**
     * @var string
     */
    public $coverPicture;
    /**
     * @var bool
     */
    public $isPublic;
    /**
     * @var bool
     */
    public $useAsYearCover;
    /**
     * @var Picture[]
     */
    public $pictures;
    /**
     * @var string
     */
    public $filename;

    public function __construct()
    {
        $this->date = new Date;
        $this->date->setTime(0, 0);
    }

    public static function getAlbumFromYearAndFoldername($year, $folder)
    {
        if (!preg_match("/^([0-9]+).([0-9]+).?(-[0-9\.]+)? (.*)$/", $folder, $matches)) {
            return null;
        }

        $album = new self;

        $album->date->setDate($year, $matches[1], $matches[2]);

        $album->year = $year;
        $album->folder = $folder;

        $album->title = $matches[4];

        $album->setNameFromTitle();

        return $album;
    }

    public function load($filename = null)
    {
        if ($filename === null) {
            $filename = $this->getJsonPath();
        }

        if (!file_exists($filename)) {
            return false;
        }

        $this->filename = $filename;

        $json = json_decode(file_get_contents($filename));

        if ($this->year === null) {
            $this->year = $json->year;
        }

        if ($this->folder === null) {
            $this->folder = $json->folder;
        }

        $this->date = new Date($json->date);
        $this->name = $json->name;
        $this->title = $json->title;
        $this->text = $json->text;
        $this->coverPicture = $json->coverPicture;
        $this->isPublic = $json->isPublic;
        $this->useAsYearCover = $json->useAsYearCover;

        $this->pictures = array();

        foreach ($json->pictures as $pictureJson) {
            $picture = new Picture;

            $picture->filename = $pictureJson->filename;
            $picture->hash = $pictureJson->hash;

            $this->pictures[] = $picture;
        }

        return true;
    }

    public function setNameFromTitle()
    {
        $this->name = preg_replace("/[^a-z0-9\-\_\.]/", "-", str_replace(array("ä", "ö", "ü", "ß"), array("ae", "oe", "ue", "ss"), mb_strtolower($this->title)));
    }

    public function save()
    {
        $filename = $this->getJsonPath();

        $filesystem = new Filesystem;

        $filesystem->dumpFile($filename, json_encode(array
        (
            "year" => $this->year,
            "folder" => $this->folder,
            "date" => $this->date->format("Y-m-d"),
            "name" => $this->name,
            "title" => $this->title,
            "text" => $this->text,
            "coverPicture" => $this->coverPicture,
            "isPublic" => $this->isPublic,
            "useAsYearCover" => $this->useAsYearCover,
            "pictures" => $this->pictures
        )));

        return $filename;
    }

    public function getSourcePath()
    {
        return sprintf("%s/%d/%s", Config::getValue(null, "source"), $this->date->format("Y"), $this->folder);
    }

    public function getJsonPath()
    {
        return sprintf("%s/albums/%d/%s.json", RESOURCES_ROOT, $this->date->format("Y"), $this->folder);
    }

    public function updatePictures()
    {
        $finder = new Finder;

        $finder->files();
        $finder->in($this->getSourcePath());
        $finder->depth("==0");
        $finder->name("/\.jpg/i");
        $finder->sortByName();

        $this->pictures = array();

        foreach ($finder as $item) {
            $picture = new Picture;

            $picture->filename = $item->getPathname();
            $picture->updateHash();

            $picture->url = sprintf("picture.jpg?year=%d&folder=%s&filename=%s", $this->year, $this->folder, $item->getFilename());

            $picture->isCover = ($this->coverPicture === $picture->hash);

            $this->pictures[] = $picture;
        }
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

        $validFiles = array();

        foreach ($this->pictures as $picture) {
            $largeFile = sprintf("%s/%s_large.jpg", $cachePath, $picture->hash);
            $smallFile = sprintf("%s/%s_small.jpg", $cachePath, $picture->hash);

            $validFiles[] = $largeFile;
            $validFiles[] = $smallFile;

            $resizer = $picture->getResizer();

            if (!is_file($largeFile)) {
                Logger::log(sprintf("Saving large version of %s to %s", $picture->filename, $largeFile));
                if (!imagejpeg($resizer->resize($largeWidth, $largeHeight), $largeFile)) {
                    throw new RuntimeException(sprintf("Unable to save picture to %s", $largeFile));
                }
            }

            if (!is_file($smallFile)) {
                Logger::log(sprintf("Saving small version of %s to %s", $picture->filename, $smallFile));
                if (!imagejpeg($resizer->resize($smallWidth, $smallHeight), $smallFile)) {
                    throw new RuntimeException(sprintf("Unable to save picture to %s", $smallFile));
                }
            }
        }

        $finder = new Finder;

        $finder->in($cachePath);
        $finder->notName("album.json");

        // Cleanup old files
        foreach ($finder as $item) {
            if (in_array($item->getPathname(), $validFiles)) {
                continue;
            }

            $filesystem->remove($item->getPathname());
        }

        $pictures = array();

        foreach ($this->pictures as $picture) {
            $pictures[] = array
            (
                "file" => $picture->hash,
                "title" => ""// TODO
            );
        }

        $filesystem->dumpFile(sprintf("%s/album.json", $cachePath), json_encode(array
        (
            "title" => $this->title,
            "text" => $this->text,
            "isPublic" => $this->isPublic,
            "useAsYearCover" => $this->useAsYearCover,
            "date" => $this->date->format("Y-m-d"),
            "pictures" => $pictures
        )));

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
            escapeshellarg(sprintf("%s@%s:%s/", $sshUser, $host, $remotePath))
        );

        $process = new Process(implode(" ", $rsyncCommand));
        $process->mustRun();

        $process = new Process(sprintf("ssh -i %s %s %s", escapeshellarg($sshKey), escapeshellarg($sshUser . "@" . $host), escapeshellarg($updateScript)));
        $process->mustRun();
    }
}