const googleForm = document.querySelector('[data-google-form]');
let googleChannel;
googleForm?.addEventListener('submit', () => {
  googleChannel?.close();
  googleForm.target = '_self';
  googleForm.elements.popup.value = '';
  if (!window.BroadcastChannel || !window.crypto?.randomUUID) return;

  const id = crypto.randomUUID();
  const popup = window.open('', id, 'popup,width=500,height=650');
  if (!popup) return;

  googleChannel = new BroadcastChannel('google-auth-' + id);
  googleChannel.onmessage = event => {
    if (typeof event.data !== 'string') return;
    const destination = new URL(event.data, location.href);
    if (destination.origin !== location.origin) return;
    googleChannel.postMessage('received');
    location.assign(destination);
  };
  googleForm.elements.popup.value = id;
  googleForm.target = id;
  popup.focus();
});
