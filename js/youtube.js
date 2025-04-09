/*
* youtube
* https://github.com/savetheinternet/Tinyboard/blob/master/js/youtube.js
*
* Don't load the YouTube player unless the video image is clicked.
* This increases performance issues when many videos are embedded on the same page.
* Currently only compatiable with YouTube.
*
* Proof of concept.
*
* Released under the MIT license
* Copyright (c) 2013 Michael Save <savetheinternet@tinyboard.org>
* Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net> 
* Copyright (c) 2013-2024 Perdedora <weav@anche.no>
*
* Usage:
*	$config['embedding'] = array();
*	$config['embedding'][0] = array(
*		'/^https?:\/\/(\w+\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9\-_]{10,11})(&.+)?$/i',
*		$config['youtube_js_html']);
*   $config['additional_javascript'][] = 'js/jquery.min.js';
*   $config['additional_javascript'][] = 'js/youtube.js';
*
*/

document.addEventListener('DOMContentLoaded', function () {
    const doEmbedVideos = (container) => {
        const videoContainers = container.querySelectorAll('div.video-container');

        videoContainers.forEach(container => {
            const provider = container.querySelector('.provider-embed')?.dataset.provider;
            const videoID = container.dataset.video;

            const link = container.querySelector('a.file');
            link.addEventListener('click', function (event) {
                event.preventDefault();

                let iframeHTML = '';
                if (provider === 'yt') {
                    iframeHTML = `
                        <iframe style="float:left;margin: 10px 20px"
                            width="360" height="270" src="//www.youtube.com/embed/${videoID}?autoplay=1&html5=1" 
                            allowfullscreen frameborder="0">
                        </iframe>
                    `;
                } else if (provider === 'dm') {
                    iframeHTML = `
                        <iframe style="float: left; margin: 10px 20px;" width="360" height="270" frameborder="0" 
                        src="https://www.dailymotion.com/embed/video/${videoID}" allowfullscreen></iframe>
                    `;
                }

                if (iframeHTML) {
                    container.innerHTML = iframeHTML;
                }
            });
        });
    };

    doEmbedVideos(document);
    document.addEventListener('new_post_js', function (e) {
        doEmbedVideos(e.detail.detail);
    });
});