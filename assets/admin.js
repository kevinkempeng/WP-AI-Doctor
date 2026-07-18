document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('.pcaied-clear').forEach((button) => {
		button.addEventListener('click', (event) => {
			if (!window.confirm('Remove the stored local and AI reports?')) {
				event.preventDefault();
			}
		});
	});

	document.querySelectorAll('[data-pcaied-toggle]').forEach((button) => {
		button.addEventListener('click', () => {
			const target = document.getElementById(button.dataset.pcaiedToggle || '');
			if (!target) {
				return;
			}

			const willOpen = target.hidden;
			target.hidden = !willOpen;
			button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
			button.textContent = willOpen ? button.dataset.labelOpen : button.dataset.labelClosed;
		});
	});

	const aiSection = document.getElementById('pcaied-ai-action');
	const aiFingerprint = document.getElementById('pcaied-ai-fingerprint');
	const aiSelection = document.getElementById('pcaied-ai-selection');
	const aiSelectionTitle = document.getElementById('pcaied-ai-selection-title');
	const aiSelectionClear = document.getElementById('pcaied-ai-selection-clear');
	const aiSubmit = document.getElementById('pcaied-ai-submit');

	document.querySelectorAll('[data-pcaied-ai-fingerprint]').forEach((button) => {
		button.addEventListener('click', () => {
			if (!aiSection || !aiFingerprint || !aiSelection || !aiSelectionTitle || !aiSubmit) {
				return;
			}

			aiFingerprint.value = button.dataset.pcaiedAiFingerprint || '';
			aiSelectionTitle.textContent = button.dataset.pcaiedAiTitle || '';
			aiSelection.hidden = false;
			aiSubmit.textContent = aiSubmit.dataset.focusedLabel || aiSubmit.textContent;
			aiSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
		});
	});

	if (aiSelectionClear) {
		aiSelectionClear.addEventListener('click', () => {
			if (!aiFingerprint || !aiSelection || !aiSelectionTitle || !aiSubmit) {
				return;
			}

			aiFingerprint.value = '';
			aiSelectionTitle.textContent = '';
			aiSelection.hidden = true;
			aiSubmit.textContent = aiSubmit.dataset.defaultLabel || aiSubmit.textContent;
		});
	}
});
