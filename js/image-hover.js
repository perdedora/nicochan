/* image-hover.js
 * This script is copied almost verbatim from https://github.com/Pashe/8chanX/blob/2-0/8chan-x.user.js
 * All I did was remove the sprintf dependency and integrate it into 8chan's Options as opposed to Pashe's.
 * I also changed initHover() to also bind on new_post.
 * Thanks Pashe for using WTFPL.
 */

if (active_page === "catalog" || active_page === "thread" || active_page === "index" ||  active_page === "ukko") {
$(document).ready(function () {

if (window.Options && Options.get_tab('general')) {
	Options.extend_tab("general",
	"<fieldset><legend>"+_('Image hover')+"</legend>"
	+ ("<label class='image-hover' id='imageHover'><input type='checkbox' /> "+_('Image hover')+"</label>")
	+ ("<label class='image-hover' id='catalogImageHover'><input type='checkbox' /> "+_('Image hover on catalog')+"</label>")
	+ ("<label class='image-hover' id='imageHoverFollowCursor'><input type='checkbox' /> "+_('Image hover should follow cursor')+"</label>")
	+ "</fieldset>");
}

$('.image-hover').on('change', function(){
	var setting = $(this).attr('id');

	localStorage[setting] = $(this).children('input').is(':checked');
});

if (!localStorage.imageHover || !localStorage.catalogImageHover || !localStorage.imageHoverFollowCursor) {
	localStorage.imageHover = 'true';
	localStorage.catalogImageHover = 'false';
	localStorage.imageHoverFollowCursor = 'true';
}

if (getSetting('imageHover')) $('#imageHover>input').prop('checked', 'checked');
if (getSetting('catalogImageHover')) $('#catalogImageHover>input').prop('checked', 'checked');
if (getSetting('imageHoverFollowCursor')) $('#imageHoverFollowCursor>input').prop('checked', 'checked');

function getFileExtension(filename) { //Pashe, WTFPL
	if (filename === undefined) {return;} // catalog
	if (filename.match(/\.([a-z0-9]+)/i)) {
		return filename.match(/\.([a-z0-9]+)/i)[1];
	} else if (filename.match(/img\.youtube\.com/)) {
		return 'Youtube';
	} else {
		return "unknown: " + filename;
	}
}

function isImage(fileExtension) { //Pashe, WTFPL
	return ($.inArray(fileExtension, ["jpg", "jpeg", "gif", "png", "webp", "jfif"]) !== -1);
}

function isVideo(fileExtension) { //Pashe, WTFPL
	return ($.inArray(fileExtension, ["webm", "mp4", "php"]) !== -1);
}

function isOnCatalog() {
	return window.active_page === "catalog";
}

function isOnThread() {
	return window.active_page === "thread";
}

function isOnUkko() {
	return window.active_page === "ukko";
}

function getSetting(key) {
	return (localStorage[key] == 'true');
}

function initImageHover() { //Pashe, influenced by tux, et al, WTFPL
	if (!getSetting("imageHover") && !getSetting("catalogImageHover")) {return;}

	var selectors = [];

	if (getSetting("imageHover")) {selectors.push("img.post-image", "canvas.post-image");}
	if (getSetting("catalogImageHover") && isOnCatalog()) {
		selectors.push(".thread-image");
		$(".theme-catalog div.thread").css("position", "inherit");
	}

	function bindEvents(el) {
		$(el).find(selectors.join(", ")).each(function () {
			if ($(this).parent().data("expanded")) {return;}

			var $this = $(this);

			$this.on("mousemove", imageHoverStart);
			$this.on("mouseout",  imageHoverEnd);
			$this.on("click",     imageHoverEnd);
		});
	}

	bindEvents(document.body);
	$(document).on('new_post', function(e, post) {
		bindEvents(post);
	});
}

function followCursor(e, hoverImage) {
	var scrollTop = $(window).scrollTop();
	var imgWidth = Number(hoverImage.css("max-width").slice(0,-2))
	var imgHeight = Number(hoverImage.css("max-height").slice(0,-2))
	var imgTop = e.pageY - (imgHeight/2);
	var windowWidth = $(window).width();
	var imgEnd = imgWidth + e.pageX;

	if (imgTop < scrollTop + 15) {
		imgTop = scrollTop + 15;
	} else if (imgTop > scrollTop + $(window).height() - imgHeight - 15) {
		imgTop = scrollTop + $(window).height() - imgHeight - 15;
	}

	if (imgEnd > windowWidth) {
		hoverImage.css({
			'left': (e.pageX + (windowWidth - imgEnd)),
			'top' : imgTop,
		});
	} else {
		hoverImage.css({
			'left': e.pageX,
			'top' : imgTop,
		});
	}
}

function imageHoverStart(e) { //Pashe, anonish, WTFPL
	var hoverImage = $("#chx_hoverImage");

	if (hoverImage.length){
		if (getSetting("imageHoverFollowCursor")) {
			followCursor(e, hoverImage);
			hoverImage.appendTo($("body"));
		}
		return;
	}

	var $this = $(this);

	var fullUrl;
	if ($this.parent().attr("href") !== undefined) {
		if ($this.parent().attr("href").match("src")) {
			fullUrl = $this.parent().attr("href");
		} else if (isOnCatalog()) {
			fullUrl = $this.attr("data-fullimage");
			if (!isImage(getFileExtension(fullUrl))) {fullUrl = $this.attr("src");}
		}
	}

	if (fullUrl === undefined) return;

	if (isVideo(getFileExtension(fullUrl))) {return;}

	hoverImage = $('<img id="chx_hoverImage" src="'+fullUrl+'" />');

	if (getSetting("imageHoverFollowCursor") && active_page !== "catalog") {
		var size = $this.parents('.file').find('.fileinfo').text().match(/\b(\d+)x(\d+)\b/);
			maxWidth = $(window).width(),
			maxHeight = $(window).height();

		var scale = Math.min(1, maxWidth / size[1], maxHeight / size[2]);
		hoverImage.css({
			"position"      : "absolute",
			"z-index"       : 101,
			"pointer-events": "none",
			"width"         : size[1] + "px",
			"height"        : size[2] + "px",
			"max-width"     : (size[1] * scale) + "px",
			"max-height"    : (size[2] * scale) + "px",
		});
	} else {
		hoverImage.css({
			"position"      : "fixed",
			"top"           : 0,
			"right"         : 0,
			"z-index"       : 101,
			"pointer-events": "none",
			"max-width"     : "100%",
			"max-height"    : "100%",
		});
	}

	if (getSetting("imageHoverFollowCursor")) {
		followCursor(e, hoverImage);
	}

	hoverImage.appendTo($("body"));

	if (isOnThread()) {$this.css("cursor", "none");}
}

function imageHoverEnd() { //Pashe, WTFPL
	$("#chx_hoverImage").remove();
}

initImageHover();
});
}
