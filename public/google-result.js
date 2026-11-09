const result = document.querySelector('[data-google-result]');
const channel = new BroadcastChannel('google-auth-' + result.dataset.googleResult);
channel.onmessage = event => {
  if (event.data === 'received') window.close();
};
channel.postMessage(result.href);
