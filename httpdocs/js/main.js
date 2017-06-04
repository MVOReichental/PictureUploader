$(function () {
    moment.locale("de");

    pollQueue();
    pollAlbums();

    $("#albums").on("click", ".upload-album", function () {
        var row = $(this).closest("tr");

        $.get("album.json?year=" + row.data("year") + "&folder=" + row.data("folder"), function (data) {
            $("#upload-modal-folder").val(data.year + "/" + data.folder);
            $("#upload-modal-title").val(data.title);
            $("#upload-modal-date").val(moment(data.date).format("YYYY-MM-DD"));
            $("#upload-modal-text").val(data.text);
            $("#upload-modal-public").prop("checked", data.isPublic);
            $("#upload-modal-year-cover").prop("checked", data.useAsYearCover);

            $("#upload-modal").modal("show").data("album", data);
        });
    });

    $("#upload-modal-form").on("submit", function (event) {
        event.preventDefault();

        var modal = $("#upload-modal");
        var album = modal.data("album");

        $.post("upload", {
            year: album.year,
            folder: album.folder,
            date: $("#upload-modal-date").val(),
            title: $("#upload-modal-title").val(),
            text: $("#upload-modal-text").val(),
            isPublic: $("#upload-modal-public").is(":checked") ? 1 : 0,
            useAsYearCover: $("#upload-modal-year-cover").is(":checked") ? 1 : 0
        }, function () {
            modal.modal("hide");
        });
    });
});

function pollQueue() {
    $.get("queue.json", function (queue) {
        for (var index = 0; index < queue.length; index++) {
            queue[index].date = moment(queue[index].date).format("LL");
        }

        $("#queue").html(Mustache.render($("#queue-template").html(), {queue: queue}));
    }).always(function () {
        setTimeout(pollQueue, 5000);
    });
}

function pollAlbums() {
    $.get("albums.json", function (albums) {
        for (var index = 0; index < albums.length; index++) {
            albums[index].date = moment(albums[index].date).format("YYYY-MM-DD");
            albums[index].formattedDate = moment(albums[index].date).format("LL");
        }

        $("#albums").html(Mustache.render($("#albums-template").html(), {albums: albums}));
    }).always(function () {
        setTimeout(pollAlbums, 5000);
    });
}