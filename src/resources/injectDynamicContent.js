function injectDynamicContent(selector, uri) {
    var xhr = new XMLHttpRequest();
    xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 300) {
            document.querySelector(selector).innerHTML = this.responseText;
        }
    };
    xhr.open("GET", uri);
    xhr.send();
}