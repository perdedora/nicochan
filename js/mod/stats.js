document.addEventListener("DOMContentLoaded", function() {
    try {
        const statistics24hData = JSON.parse(document.getElementById('statistics-24h-data').textContent);
        renderHourlyChart(statistics24hData);

        const statisticsWeekLabels = JSON.parse(document.getElementById('statistics-week-labels-data').textContent);
        const statisticsWeekPast = JSON.parse(document.getElementById('statistics-week-past-data').textContent);
        const statisticsWeek = JSON.parse(document.getElementById('statistics-week-data').textContent);
        renderWeeklyChart(statisticsWeekLabels, statisticsWeekPast, statisticsWeek);
    } catch (error) {
        console.error("Error parsing JSON data:", error);
    }
});

function renderHourlyChart(statistics24h) {
    if (!Array.isArray(statistics24h)) {
        console.error("Invalid data format for 24-hour statistics.");
        return;
    }

    const data_24h = {
        labels: ["AM", "1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "PM", "1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11"],
        series: [statistics24h]
    };

    const options_24h = {
        width: 800,
        height: 300
    };

    new Chartist.Line('#hourly', data_24h, options_24h);
}

function renderWeeklyChart(labels, pastData, currentData) {
    if (!Array.isArray(labels) || !Array.isArray(pastData) || !Array.isArray(currentData)) {
        console.error("Invalid data format for weekly statistics.");
        return;
    }

    const data_week = {
        labels: labels,
        series: [pastData, currentData]
    };

    const options_week = {
        width: 800,
        height: 300,
        seriesBarDistance: 10,
        reverseData: true,
        horizontalBars: true,
        axisY: {
            offset: 70
        }
    };

    const chart = new Chartist.Bar('#week', data_week, options_week);

    const tooltip = Vichan.createElement('div', {
        className: 'chart-tooltip',
        attributes: {
            style: 'position: absolute; display: none; background-color: #333; color: #fff; padding: 5px; border-radius: 3px;'
        },
        parent: document.body
    });

    chart.on('draw', function (data) {
        if (data.type === 'bar') {
            data.element._node.addEventListener('mouseenter', function (event) {
                tooltip.style.display = 'block';
                tooltip.innerHTML = `${data.value.x} posts`;
            });

            data.element._node.addEventListener('mouseleave', function () {
                tooltip.style.display = 'none';
            });

            data.element._node.addEventListener('mousemove', function (event) {
                tooltip.style.left = (event.pageX + 10) + 'px';
                tooltip.style.top = (event.pageY - 20) + 'px';
            });
        }
    });
}