$(function () {
    moment.locale("de");

    poll();
});

function poll() {
    $.get("status.json", function (queue) {
        for (var index = 0; index < queue.length; index++) {
            queue[index].date = moment(queue[index].date).format("LL");
        }

        $("#status").html(Mustache.render($("#status-template").html(), {queue: queue}));
    }).always(function () {
        setTimeout(poll, 5000);
    });
}