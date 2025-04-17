document.addEventListener("DOMContentLoaded", function() {
    const secondsLeft = parseInt(document.getElementById('ban-expires')?.dataset.expires, 10);
    const countdown = document.getElementById("countdown");
    if (countdown) {
        const end = new Date().getTime() + secondsLeft * 1000;

        function updateExpiresTime() {
            countdown.textContent = until(end);
        }

        function until(end) {
            const now = new Date().getTime();
            const diff = Math.round((end - now) / 1000);

            if (diff < 0) {
                document.getElementById("expires").textContent = _("has since expired. Refresh the page to continue.");
                clearInterval(int);
                return "";
            } else if (diff < 60) {
                return diff + " " + (diff === 1 ? _("second") : _("seconds"));
            } else if (diff < 60 * 60) {
                return Math.round(diff / 60) + " " + (Math.round(diff / 60) === 1 ? _("minute") : _("minutes"));
            } else if (diff < 60 * 60 * 24) {
                return Math.round(diff / (60 * 60)) + " " + (Math.round(diff / (60 * 60)) === 1 ? _("hour") : _("hours"));
            } else if (diff < 60 * 60 * 24 * 7) {
                return Math.round(diff / (60 * 60 * 24)) + " " + (Math.round(diff / (60 * 60 * 24)) === 1 ? _("day") : _("days"));
            } else if (diff < 60 * 60 * 24 * 365) {
                return Math.round(diff / (60 * 60 * 24 * 7)) + " " + (Math.round(diff / (60 * 60 * 24 * 7)) === 1 ? _("week") : _("weeks"));
            } else {
                return Math.round(diff / (60 * 60 * 24 * 365)) + " " + (Math.round(diff / (60 * 60 * 24 * 365)) === 1 ? _("year") : _("years"));
            }
        }

        updateExpiresTime();
        const int = setInterval(updateExpiresTime, 1000);
    }
});