document.addEventListener('click', async event => {
  const button = event.target.closest('[data-copy-text], [data-copy-only]');
  if (!button || button.dataset.copying) return;
  button.dataset.copying = 'true';
  const hasCustom = button.dataset.copyMode === 'alternating-copy';
  let next = button.dataset.nextCopy;
  let key;
  if (hasCustom) {
    const query = new URLSearchParams(location.search);
    const block = button.dataset.copyKey || [
      query.get('r'), query.get('slug') || query.get('name'),
      Array.from(document.querySelectorAll('[data-copy-mode="alternating-copy"]')).indexOf(button)
    ];
    key = 'uptime.copy.v1.' + JSON.stringify([document.body.dataset.reader, block]);
    try { next = localStorage.getItem(key) ?? next; } catch {}
  }
  const useCustom = button.dataset.copyMode === 'custom-copy' || (hasCustom && next === 'custom');
  const remote = useCustom || button.hasAttribute('data-copy-only');
  const payload = remote ? fetch('index.php?r=clipboard', {
    method: 'POST',
    cache: 'no-store',
    body: new URLSearchParams({ref: button.dataset.copyRef, ticket: button.dataset.copyTicket, part: useCustom ? 'custom' : 'command'})
  }).then(async response => {
    if (!response.ok) throw new Error('Clipboard text unavailable');
    const text = await response.json();
    if (typeof text !== 'string') throw new Error('Invalid clipboard text');
    return text;
  }) : Promise.resolve(button.dataset.copyText);
  let copied = false;
  try {
    if (remote && window.ClipboardItem && navigator.clipboard?.write) {
      const blob = payload.then(text => new Blob([text], { type: 'text/plain' }));
      await navigator.clipboard.write([new ClipboardItem({ 'text/plain': blob })]);
    } else {
      await navigator.clipboard.writeText(await payload);
    }
    copied = true;
  } catch {
    const field = document.createElement('textarea');
    try {
      field.value = await payload;
    } catch {
      delete button.dataset.copying;
      window.alert('Could not load the clipboard text. Reload the page and try again.');
      return;
    }
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
  if (copied && hasCustom) {
    button.dataset.nextCopy = useCustom ? 'command' : 'custom';
    try { localStorage.setItem(key, button.dataset.nextCopy); } catch {}
  }
  let status = button.parentElement.querySelector('[role="status"]');
  if (!status) {
    status = document.createElement('span');
    status.className = 'sr-only';
    status.setAttribute('role', 'status');
    button.after(status);
  }
  status.textContent = copied ? 'Copied' : 'Copy failed';
  if (copied) {
    button.closest('[data-copy-package]')?.querySelector('a[download]')?.click();
  }
});
