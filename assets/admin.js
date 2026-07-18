document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('.pcaied-clear').forEach((button) => {
		button.addEventListener('click', (event) => {
			if (!window.confirm('Remove the stored local and AI reports?')) {
				event.preventDefault();
			}
		});
	});
});

