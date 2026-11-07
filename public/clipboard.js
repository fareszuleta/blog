document.addEventListener('click', async event => {
  const button = event.target.closest('[data-copy-text]');
  if (!button) return;
  const text = button.dataset.copyText;
  let copied = false;
  try {
    await navigator.clipboard.writeText(text);
    copied = true;
  } catch {
    const field = document.createElement('textarea');
    field.value = text;
    field.style.cssText = 'position:fixed;top:0;opacity:0';
    document.body.append(field);
    field.select();
    try {
      copied = document.execCommand('copy');
    } finally {
      field.remove();
      button.focus();
    }
  }
  button.textContent = copied ? 'Copied ✓' : 'Select text to copy';
  button.setAttribute('aria-live', 'polite');
  clearTimeout(Number(button.dataset.timer));
  button.dataset.timer = setTimeout(() => { button.textContent = 'Copy'; }, 2200);
});

