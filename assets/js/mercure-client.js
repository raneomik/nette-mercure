
document.addEventListener('DOMContentLoaded', () => {

	const mercureUrlPlaceholder = document.querySelector('[data-mercure-url]');

	if (!mercureUrlPlaceholder) {
		console.error("Mercure URL not found in any data-mercure-url attribute.");
		return;
	}

	const mercureUrl = mercureUrlPlaceholder.dataset.mercureUrl;

	const eventSource = new EventSource(mercureUrl);
	console.log("Mercure EventSource connected to:", mercureUrl);

	const placeholders = document.querySelectorAll("[data-mercure]");
	eventSource.onmessage = event => {
		for (const placeholder of placeholders) {
			placeholder.textContent = event.data;
		}
	}

	eventSource.onerror = event => {
		console.error("Mercure connection error:", event);
	}
});
