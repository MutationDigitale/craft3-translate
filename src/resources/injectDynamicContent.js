function injectDynamicContent(selector, uri) {
	var xhr = new XMLHttpRequest();
	xhr.onload = function () {
		if (xhr.status >= 200 && xhr.status < 300) {
			var newElement = document.createElement('div');
			newElement.innerHTML = this.responseText;

			var targetElement = document.querySelector(selector);
			targetElement.parentNode.replaceChild(newElement.firstElementChild, targetElement);
		}
	};
	xhr.open("GET", uri);
	xhr.send();
}
