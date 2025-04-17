document.addEventListener('DOMContentLoaded', () => { 
	document.getElementById('password').value = localStorage.getItem('password');
});