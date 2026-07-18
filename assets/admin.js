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

	document.querySelectorAll('[data-pcaied-jump-to]').forEach((link) => {
		link.addEventListener('click', (event) => {
			const target = document.getElementById(link.dataset.pcaiedJumpTo || '');
			if (!target) {
				return;
			}

			event.preventDefault();
			let ancestor = target.parentElement;
			while (ancestor) {
				if (ancestor.tagName === 'DETAILS') {
					ancestor.open = true;
				}
				ancestor = ancestor.parentElement;
			}

			target.classList.add('pcaied-finding-jump-highlight');
			target.focus({ preventScroll: true });
			target.scrollIntoView({ behavior: 'smooth', block: 'center' });
			window.setTimeout(() => {
				target.classList.remove('pcaied-finding-jump-highlight');
			}, 2200);
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
