$(function () {
    moment.locale("de");

    updateData();
    setInterval(updateData, 5000);

    $("#upload-modal").find(".modal-body a").click(function (event) {
        event.preventDefault();
        $(this).tab("show")
    });

    $("#albums").on("click", ".upload-album", function () {
        $("#upload-modal").data("loading", true);
        $("#albums").find(".upload-album").prop("disabled", true);

        var row = $(this).closest("tr");

        $.get("album.json?year=" + row.data("year") + "&folder=" + row.data("folder"), function (data) {
            $("#upload-modal-folder").val(data.year + "/" + data.folder);
            $("#upload-modal-title").val(data.title);
            $("#upload-modal-date").val(moment(data.date).format("YYYY-MM-DD"));
            $("#upload-modal-text").val(data.text);
            $("#upload-modal-public").prop("checked", data.isPublic);
            $("#upload-modal-year-cover").prop("checked", data.useAsYearCover);

            $("#upload-modal-tab-albumcover").html(Mustache.render($("#upload-modal-tab-albumcover-template").html(), {
                pictures: data.pictures
            }));

            $("#upload-modal").modal("show").data("album", data);
        }).always(function () {
            $("#upload-modal").data("loading", false);
            $("#albums").find(".upload-album").prop("disabled", false);
        });
    });

    $("#upload-modal-tab-albumcover").on("click", ".thumbnail", function () {
        $("#upload-modal-tab-albumcover").find(".active-album-cover").addClass("hidden");

        $(this).find(".active-album-cover").removeClass("hidden");
    });

    $("#upload-modal-form").on("submit", function (event) {
        event.preventDefault();

        var modal = $("#upload-modal");
        var album = modal.data("album");

        var coverPicture = null;

        $("#upload-modal-tab-albumcover").find(".thumbnail").each(function () {
            if ($(this).hasClass("active")) {
                coverPicture = $(this).data("hash");
            }
        });

        $.post("upload", {
            year: album.year,
            folder: album.folder,
            date: $("#upload-modal-date").val(),
            title: $("#upload-modal-title").val(),
            text: $("#upload-modal-text").val(),
            coverPicture: coverPicture,
            isPublic: $("#upload-modal-public").is(":checked") ? 1 : 0,
            useAsYearCover: $("#upload-modal-year-cover").is(":checked") ? 1 : 0
        }, function () {
            updateData();

            modal.modal("hide");
        });
    });
});

function updateData() {
    $.get("queue.json", function (queue) {
        for (var index = 0; index < queue.length; index++) {
            queue[index].date = moment(queue[index].date).format("LL");
        }

        $("#queue").html(Mustache.render($("#queue-template").html(), {queue: queue}));
    });

    $.get("albums.json", function (albums) {
        for (var index = 0; index < albums.length; index++) {
            albums[index].date = moment(albums[index].date).format("YYYY-MM-DD");
            albums[index].formattedDate = moment(albums[index].date).format("LL");
        }

        var albumsContainer = $("#albums");

        albumsContainer.html(Mustache.render($("#albums-template").html(), {albums: albums}));

        if ($("#upload-modal").data("loading")) {
            albumsContainer.find(".upload-album").prop("disabled", true);
        }
    });
}