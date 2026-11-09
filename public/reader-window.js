const launch = document.querySelector('[data-reader-launch]');
if (launch) {
  let popup;
  launch.addEventListener('click', event => {
    popup = window.open(launch.href, 'uptimeReaderLogin', 'popup,width=490,height=680');
    if (popup) {
      event.preventDefault();
      popup.focus();
    }
  });
  window.addEventListener('message', event => {
    if (event.origin !== location.origin || event.source !== popup || event.data !== 'uptime-reader-signed-in') return;
    const skill = new URL(launch.href).searchParams.get('skill');
    const query = new URLSearchParams(skill ? {r: 'skill', name: skill} : {r: 'skills'});
    popup.close();
    location.assign('index.php?' + query);
  });
}
const done = document.querySelector('[data-reader-done]');
if (done) {
  if (window.opener && !window.opener.closed) window.opener.postMessage('uptime-reader-signed-in', location.origin);
  else location.replace(done.href);
}
