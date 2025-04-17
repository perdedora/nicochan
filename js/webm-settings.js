/* This file is dedicated to the public domain; you may do as you wish with it. */

if (typeof _ === 'undefined') {
    var _ = function (a) { return a; };
}

// Default settings
const defaultSettings = {
    videoexpand: true,
    videohover: false,
    videovolume: 1.0
};

// Non-persistent settings for when localStorage is absent/disabled
const tempSettings = {};

// Scripts obtain settings by calling this function
function getSetting(name) {
    if (typeof localStorage !== 'undefined' && localStorage[name] !== undefined) {
        return JSON.parse(localStorage[name]);
    } else {
        return tempSettings[name] !== undefined ? tempSettings[name] : defaultSettings[name];
    }
}

// Settings should be changed with this function
function changeSetting(name, value) {
    if (typeof localStorage !== 'undefined') {
        localStorage[name] = JSON.stringify(value);
    } else {
        tempSettings[name] = value;
    }
}

// Create settings menu
const settingsMenu = document.createElement("div");
let prefix = "", suffix = "", style = "";

if (window.Options) {
    const tab = Options.add_tab("webm", "video-camera", _("WebM"));
    tab.content.appendChild(settingsMenu);
} else {
    prefix = '<a class="unimportant">' + _('WebM Settings') + '</a>';
    settingsMenu.style.textAlign = "right";
    settingsMenu.style.background = "inherit";
    suffix = '</div>';
    style = 'display: none; text-align: left; position: absolute; right: 1em; margin-left: -999em; margin-top: -1px; padding-top: 1px; background: inherit;';
}

settingsMenu.innerHTML = `${prefix}
  <div style="${style}">
    <label><input type="checkbox" name="videoexpand">${_('Expand videos inline')}</label><br>
    <label><input type="checkbox" name="videohover">${_('Play videos on hover')}</label><br>
    <label><input type="range" name="videovolume" min="0" max="1" step="0.01" style="width: 4em; height: 1ex; vertical-align: middle; margin: 0px;">${_('Default volume')}</label><br>
  ${suffix}`;

function refreshSettings() {
    const settingsItems = settingsMenu.querySelectorAll("input");
    settingsItems.forEach(control => {
        if (control.type === "checkbox") {
            control.checked = getSetting(control.name);
        } else if (control.type === "range") {
            control.value = getSetting(control.name);
        }
    });
}

function setUpControl(control) {
    control.addEventListener("change", function () {
        if (control.type === "checkbox") {
            changeSetting(control.name, control.checked);
        } else if (control.type === "range") {
            changeSetting(control.name, control.value);
        }
    });
}

refreshSettings();
const settingsItems = settingsMenu.querySelectorAll("input");
settingsItems.forEach(setUpControl);

if (!window.Options) {
    settingsMenu.addEventListener("mouseover", function () {
        refreshSettings();
        const anchor = settingsMenu.querySelector("a");
        if (anchor) anchor.style.fontWeight = "bold";
        const menuDiv = settingsMenu.querySelector("div");
        if (menuDiv) menuDiv.style.display = "block";
    });

    settingsMenu.addEventListener("mouseout", function () {
        const anchor = settingsMenu.querySelector("a");
        if (anchor) anchor.style.fontWeight = "normal";
        const menuDiv = settingsMenu.querySelector("div");
        if (menuDiv) menuDiv.style.display = "none";
    });
}
