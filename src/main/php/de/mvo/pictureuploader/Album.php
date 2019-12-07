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
    /**
     * @var bool
     */
    public $isUploaded = false;

    public function __construct()
    {
        $this->date = new Date;
        $this->date->setTime(0, 0);
    }

    public static function getAlbumFromYearAndFoldername($year, $folder)
    {
        if (!preg_match("/^([0-9]+)\.([0-9]+)\.?(-[0-9\.]+)? (.*)$/", $folder, $matches)) {
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
        $this->isUploaded = $json->isUploaded;

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
            "isUploaded" => $this->isUploaded,
            "pictures" => $this->pictures
        )));

        return $filename;
    }

    public function getSourcePath()
    {
        return sprintf("%s/%d/%s", Config::getValue("source"), $this->date->format("Y"), $this->folder);
    }

    public function getJsonPath()
    {
        return sprintf("%s/%d/%s.json", Config::getValue("albums-root"), $this->date->format("Y"), $this->folder);
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
        $cachePath = sprintf("%s/%d/%s", Config::getValue("pictures-cache"), $this->date->format("Y"), $this->name);

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0775, true);
        }

        list($largeWidth, $largeHeight) = explode("x", Config::getValue("large-size", "1500x1000"));
        list($smallWidth, $smallHeight) = explode("x", Config::getValue("small-size", "600x200"));

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
                "hash" => $picture->hash,
                "title" => ""// TODO
            );
        }

        $filesystem->dumpFile(sprintf("%s/album.json", $cachePath), json_encode(array
        (
            "title" => $this->title,
            "text" => $this->text,
            "isPublic" => $this->isPublic,
            "useAsYearCover" => $this->useAsYearCover,
            "coverPicture" => $this->coverPicture,
            "date" => $this->date->format("Y-m-d"),
            "pictures" => $pictures
        )));

        $remotePath = sprintf("%s/%s/%s", Config::getValue("remote-pictures-dir"), $this->date->format("Y"), $this->name);
        $sshKey = Config::getValue("ssh-key");
        $sshUser = Config::getValue("ssh-user");
        $host = Config::getValue("host");
        $updateScript = Config::getValue("update-script");

        $rsyncCommand = array
        (
            "rsync",
            "-avz",
            "--delete",
            sprintf("--rsync-path %s", escapeshellarg(sprintf("mkdir -p %s && rsync", $remotePath))),
            sprintf("-e %s", escapeshellarg(sprintf("ssh -i %s", $sshKey))),
            escapeshellarg(sprintf("%s/", $cachePath)),
            escapeshellarg(sprintf("%s@%s:%s/", $sshUser, $host, $remotePath))
        );

        $rsyncCommand = implode(" ", $rsyncCommand);

        Logger::log(sprintf("Executing rsync: %s", $rsyncCommand));

        $process = new Process($rsyncCommand);
        $process->mustRun(function ($type, $data) {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $data);
        });

        $process = new Process(sprintf("ssh -i %s %s %s", escapeshellarg($sshKey), escapeshellarg($sshUser . "@" . $host), escapeshellarg($updateScript)));
        $process->mustRun(function ($type, $data) {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $data);
        });

        // Load again before save to prevent overwriting changes done while uploading
        $this->load();

        $this->isUploaded = true;

        $this->save();
    }
}