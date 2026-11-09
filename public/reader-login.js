const readerForm = document.querySelector('[data-reader-email]');
const emailField = readerForm.elements.email;
const passwordField = readerForm.elements.password;
const passwordLabel = passwordField.closest('label');
const submitButton = readerForm.querySelector('button');
const finalLabel = submitButton.textContent;
function showEmailStep() {
  passwordField.value = '';
  passwordField.disabled = true;
  passwordLabel.hidden = true;
  submitButton.textContent = 'Next';
}
showEmailStep();
emailField.addEventListener('input', showEmailStep);
readerForm.addEventListener('submit', event => {
  if (!passwordField.disabled) return;
  event.preventDefault();
  passwordField.disabled = false;
  passwordLabel.hidden = false;
  submitButton.textContent = finalLabel;
  passwordField.focus();
});
