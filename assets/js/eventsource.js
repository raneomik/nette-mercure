
const mercureUrl = document.querySelector('div[data-mercure*]').dataset.mercureUrl;
const eventSource = new EventSource(mercureUrl);

const placeholders = document.querySelectorAll("[data-mercure=]");
eventSource.onmessage = event => {
	for (const placeholder of placeholders) {
		placeholder.textContent = event.data;
	}
}
