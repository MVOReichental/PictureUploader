<?php
use de\mvo\pictureuploader\Date;
use de\mvo\pictureuploader\Queue;
use de\mvo\pictureuploader\QueueItem;

require_once __DIR__ . "/../bootstrap.php";

$router = new AltoRouter;

$router->map("GET", "/", "html");
$router->map("GET", "/status.json", "status");
$router->map("POST", "/upload", "upload");

$match = $router->match();
if ($match === false) {
    http_response_code(404);
    echo "Resource not found!";
    exit;
}

switch ($match["target"]) {
    case "html":
        readfile("status.html");
        break;
    case "status":
        header("Content-Type: application/json");
        echo json_encode((array)Queue::get());
        break;
    case "upload":
        if (!isset($_POST["year"]) or !isset($_POST["folder"]) or !$_POST["year"] or !$_POST["folder"]) {
            http_response_code(400);
            echo "Missing or empty 'year' and 'folder' parameters!";
            exit;
        }

        if (!preg_match("/^([0-9]+).([0-9]+).?(-[0-9\.]+)? (.*)$/", $_POST["folder"], $matches)) {
            http_response_code(400);
            printf("Can not parse folder name: %s", $_POST["folder"]);
            exit;
        }

        $year = $_POST["year"];
        $month = $matches[1];
        $day = $matches[2];

        if (!checkdate($month, $day, $year)) {
            http_response_code(400);
            printf("Invalid date: %s-%s-%s", $year, $month, $day);
            exit;
        }

        $title = $matches[4];

        $albumName = preg_replace("/[^a-z0-9\-\_\.]/", "-", str_replace(array("ä", "ö", "ü", "ß"), array("ae", "oe", "ue", "ss"), mb_strtolower($title)));

        $queueItem = new QueueItem;

        $queueItem->date = new Date;
        $queueItem->date->setDate($year, $month, $day);

        $queueItem->folder = $_POST["folder"];
        $queueItem->name = $albumName;
        $queueItem->title = $title;

        $queueItem->save();
        break;
}