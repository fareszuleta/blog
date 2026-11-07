document.addEventListener('click', async event => {
  const button = event.target.closest('[data-copy-text]');
  if (!button || button.dataset.copying) return;
  button.dataset.copying = 'true';
  const hasCustom = button.dataset.customText !== undefined;
  const useCustom = hasCustom && button.dataset.nextCopy === 'custom';
  const text = useCustom ? button.dataset.customText : button.dataset.copyText;
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
    } catch {
      copied = false;
    } finally {
      field.remove();
      button.focus();
    }
  }
  delete button.dataset.copying;
  if (copied && hasCustom) button.dataset.nextCopy = useCustom ? 'command' : 'custom';
  const nextLabel = hasCustom
    ? (button.dataset.nextCopy === 'custom' ? 'Copy custom text' : 'Copy command')
    : 'Copy';
  button.textContent = copied ? (hasCustom ? nextLabel : 'Copied ✓') : 'Copy failed';
  button.setAttribute('aria-live', 'polite');
  clearTimeout(Number(button.dataset.timer));
  button.dataset.timer = setTimeout(() => { button.textContent = nextLabel; }, 2200);
});

