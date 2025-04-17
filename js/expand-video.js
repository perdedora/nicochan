/* This file is dedicated to the public domain; you may do as you wish with it. */
/* Note: This code expects the global variable configRoot to be set. */

function setupVideo(thumb, url) {
    if (thumb.videoAlreadySetUp) return;
    thumb.videoAlreadySetUp = true;

    let video = null;
    let videoContainer;
    let expanded = false;
    let hovering = false;
    let mouseDown = false;
    let loop = true;

    const fileInfo = thumb.parentNode.querySelector(".fileinfo");

    const unexpand = () => {
        if (!expanded) return;
        expanded = false;
        video.pause();
        videoContainer.style.display = "none";
        thumb.style.display = "inline";
        video.style.maxWidth = video.style.maxHeight = "inherit";
    };

    const unhover = () => {
        if (!hovering) return;
        hovering = false;
        video.pause();
        videoContainer.style.display = "none";
        video.style.maxWidth = video.style.maxHeight = "inherit";
    };

    const getVideo = () => {
        if (video) return;

        video = Vichan.createElement("video", {
            attributes: { src: url, loop: loop },
            text: _("Your browser does not support HTML5 video.")
        });

        const videoHide = Vichan.createElement("img", {
            attributes: {
                src: `${configRoot}static/collapse.gif`, alt: "[ - ]", title: _("Collapse video"),
                style: 'margin-left: -15px; float: left',
            },
            onClick: unexpand
        });

        videoContainer = Vichan.createElement("div", {
            attributes: {
                style: 'padding-left: 15px; display: none',
            }
        });

        videoContainer.append(videoHide, video);
        thumb.parentNode.insertBefore(videoContainer, thumb.nextSibling);

        video.addEventListener("mousedown", (e) => { if (e.button === 0) mouseDown = true; });
        video.addEventListener("mouseup", (e) => { if (e.button === 0) mouseDown = false; });
        video.addEventListener("mouseout", (e) => {
            if (mouseDown && e.clientX - video.getBoundingClientRect().left <= 0) {
                unexpand();
            }
            mouseDown = false;
        });
    };

    thumb.addEventListener("click", (e) => {
        if (getSetting("videoexpand") && !e.shiftKey && !e.ctrlKey && !e.altKey && !e.metaKey) {
            getVideo();
            expanded = true;
            hovering = false;

            Object.assign(video.style, {
                position: "static",
                pointerEvents: "inherit",
                display: "inline",
                maxWidth: "100%",
                maxHeight: `${window.innerHeight}px`
            });

            videoContainer.style.display = "block";
            thumb.style.display = "none";
            video.muted = getSetting("videovolume") === 0;
            video.volume = getSetting("videovolume");
            video.controls = true;

            if (video.readyState === 0) {
                video.addEventListener("loadedmetadata", expandVideo);
            } else {
                setTimeout(expandVideo, 0);
            }

            video.play();
            e.preventDefault();
        }
    });

    const expandVideo = () => {
        const bottom = video.getBoundingClientRect().bottom;
        if (bottom > window.innerHeight) {
            window.scrollBy(0, bottom - window.innerHeight);
        }
        video.volume = Math.max(getSetting("videovolume") - 0.001, 0);
        video.volume = getSetting("videovolume");
    };

    thumb.addEventListener("mouseover", () => {
        if (getSetting("videohover")) {
            getVideo();
            expanded = false;
            hovering = true;

            const maxWidth = Math.max(document.documentElement.clientWidth - thumb.getBoundingClientRect().right - 20, 250);

            Object.assign(video.style, {
                position: "absolute",
                maxWidth: `${maxWidth}px`,
                zIndex: '3',
                maxHeight: "100%",
                pointerEvents: "none",
                display: "inline"
            });

            videoContainer.style.display = "inline";
            video.muted = getSetting("videovolume") === 0;
            video.volume = getSetting("videovolume");
            video.controls = false;
            video.play();
        }
    });

    thumb.addEventListener("mouseout", unhover);

    thumb.addEventListener("wheel", (e) => {
        if (getSetting("videohover")) {
            let volume = getSetting("videovolume");
            volume = Math.max(0, Math.min(1, volume + (e.deltaY < 0 ? 0.1 : -0.1)));
            if (video) {
                video.muted = volume === 0;
                video.volume = volume;
            }
            changeSetting("videovolume", volume);
            e.preventDefault();
        }
    });

    const loopControls = [
        Vichan.createElement("span", {
            text: _("[play once]"),
            attributes: { style: 'white-space: nowrap; cursor: pointer' },
            onClick: () => setLoopControl(0)
        }),
        Vichan.createElement("span", {
            text: _("[loop]"),
            attributes: { style: 'font-weight: bold; white-space: nowrap; cursor: pointer' },
            onClick: () => setLoopControl(1)
        })
    ];

    const setLoopControl = (index) => {
        loop = index !== 0;
        thumb.href = thumb.href.replace(/([?&])loop=\d+/, `$1loop=${index}`);
        if (video) {
            video.loop = loop;
            if (loop && video.currentTime >= video.duration) {
                video.currentTime = 0;
            }
        }
        loopControls.forEach((control, i) => {
            control.style.fontWeight = i === index ? "bold" : "inherit";
        });
    };

    loopControls.forEach((control, i) => {
        fileInfo.append(" ", control);
    });
}

async function setupVideosIn(element) {
    const thumbs = element.querySelectorAll("a.file");
    thumbs.forEach(thumb => {
        if (/\.(webm|mp4|mp3|ogg|wav|flac)$/.test(thumb.pathname)) {
            setupVideo(thumb, thumb.href);
        } else {
            const match = thumb.search.match(/\bv=([^&]*)/);
            if (match) {
                const url = decodeURIComponent(match[1]);
                if (/\.(webm|mp4|mp3|ogg|wav|flac)$/.test(url)) setupVideo(thumb, url);
            }
        }
    });
}

document.addEventListener("DOMContentLoaded", () => {
    if (typeof settingsMenu !== "undefined" && typeof Options === "undefined") {
        document.body.insertBefore(settingsMenu, document.querySelector("hr"));
    }

    setupVideosIn(document);

    document.addEventListener('new_post_js', (post) => {
        setupVideosIn(post.detail.detail);
    });
});
