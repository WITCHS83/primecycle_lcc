(function(){
  const KEY='lifecycle.theme';
  const root=document.documentElement;
  const toggle=document.getElementById('themeToggle');
  function paint(){ if(!toggle) return; toggle.textContent = root.getAttribute('data-theme')==='dark' ? '☀️ Light' : '🌙 Dark'; }
  paint();
  if(toggle){
    toggle.addEventListener('click',()=>{
      const dark = root.getAttribute('data-theme')==='dark';
      if(dark){ root.removeAttribute('data-theme'); localStorage.setItem(KEY,'light'); }
      else { root.setAttribute('data-theme','dark'); localStorage.setItem(KEY,'dark'); }
      paint();
    });
  }
})();