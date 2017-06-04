<?php
use de\mvo\pictureuploader\Album;
use de\mvo\pictureuploader\Albums;
use de\mvo\pictureuploader\Config;

require_once __DIR__ . "/../bootstrap.php";

$router = new AltoRouter;

$router->map("GET", "/", "html");
$router->map("GET", "/albums.json", "albums");
$router->map("GET", "/album.json", "album");
$router->map("GET", "/queue.json", "queue");
$router->map("POST", "/upload", "upload");

$match = $router->match();
if ($match === false) {
    http_response_code(404);
    echo "Resource not found!";
    exit;
}

switch ($match["target"]) {
    case "html":
        readfile("view.html");
        break;
    case "albums":
        header("Content-Type: application/json");
        echo json_encode((array)Albums::get());
        break;
    case "album":
        if (!isset($_GET["year"]) or !isset($_GET["folder"]) or !$_GET["year"] or !$_GET["folder"]) {
            http_response_code(400);
            echo "Missing or empty 'year' and 'folder' parameters!";
            break;
        }

        header("Content-Type: application/json");

        $album = Album::getAlbumFromYearAndFoldername($_GET["year"], $_GET["folder"]);

        $album->load();

        echo json_encode($album);
        break;
    case "queue":
        header("Content-Type: application/json");
        echo json_encode((array)Albums::getInQueue());
        break;
    case "upload":
        if (!isset($_POST["year"]) or !isset($_POST["folder"]) or !$_POST["year"] or !$_POST["folder"]) {
            http_response_code(400);
            echo "Missing or empty 'year' and 'folder' parameters!";
            break;
        }

        $album = Album::getAlbumFromYearAndFoldername($_POST["year"], $_POST["folder"]);

        if ($album === null) {
            http_response_code(400);
            printf("Can not parse folder name: %s", $_POST["folder"]);
            exit;
        }

        if (isset($_POST["title"])) {
            $album->title = $_POST["title"];

            $album->setNameFromTitle();
        }

        if (isset($_POST["text"])) {
            $album->text = $_POST["text"];
        }

        if (isset($_POST["isPublic"])) {
            $album->isPublic = (bool)$_POST["isPublic"];
        }

        if (isset($_POST["useAsYearCover"])) {
            $album->useAsYearCover = (bool)$_POST["useAsYearCover"];
        }

        $album->save();
        $album->save(sprintf("%s/%s.json", Config::getValue(null, "queue"), uniqid()));
        break;
}