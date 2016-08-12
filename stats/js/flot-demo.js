$(function () {

    var container = $("#flot-line-chart-moving");

    var maximum = container.outerWidth() / 2 || 300;

    var data = [];

    function getRandomData() {

        if (data.length) data = data.slice(1);

        while (data.length < maximum) data.push(0);

        var res = [];
        for (var i = 0; i < data.length; ++i) res.push([i, data[i]])
        return res;
    }

    var series = [{
        label: '当前连接数',
        data: getRandomData(),
        lines: {fill: true}
    }];

    //

    var plot = $.plot(container, series, {
        grid: {
            borderWidth: 1,
            minBorderMargin: 20,
            labelMargin: 10,
            backgroundColor: {
                colors: ["#fff", "#e4f4f4"]
            },
            margin: {
                top: 8,
                bottom: 20,
                left: 20
            },
            markings: function (axes) {
                var markings = [];
                var xaxis = axes.xaxis;
                for (var x = Math.floor(xaxis.min); x < xaxis.max; x += xaxis.tickSize * 2) {
                    markings.push({
                        xaxis: {
                            from: x,
                            to: x + xaxis.tickSize
                        },
                        color: "rgba(232, 232, 255, 0.2)"
                    });
                }
                return markings;
            }
        },
        xaxis: {
            tickFormatter: function () {
                return "";
            }
        },
        yaxis: {
            min: 0,
            max: 50
        },
        legend: {
            show: true
        }
    });

    // Update the random dataset at 25FPS for a smoothly-animating chart

    setInterval(function updateRandom() {
        $.getJSON('/view', function (response) {
            data.shift();
            data.push(response.connection_num);

            var formatData = [];
            for (var i = 0; i < data.length; ++i) {
                formatData.push([i, data[i]]);
            }
            series[0].data = formatData;
            localStorage.flot = JSON.stringify(formatData);
            plot.setData(series);
            plot.draw();
        });
    }, 400);
});