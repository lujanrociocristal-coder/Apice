
/* ===== Animación de marca ÁPICE (cortina de bienvenida + repetir al tocar el logo) ===== */
(function(){
  var EASE="cubic-bezier(.16,.84,.30,1)", EDGE=288, running=false, actx=null;
  function el(id){return document.getElementById(id);}
  function ac(){ if(!actx){try{actx=new (window.AudioContext||window.webkitAudioContext)();}catch(e){}} if(actx&&actx.state==="suspended")actx.resume(); return actx; }
  function whoosh(t){ var c=ac(); if(!c)return; var n=c.sampleRate*0.32,b=c.createBuffer(1,n,c.sampleRate),d=b.getChannelData(0),i;
    for(i=0;i<n;i++)d[i]=(Math.random()*2-1)*(1-i/n);
    var s=c.createBufferSource();s.buffer=b; var lp=c.createBiquadFilter();lp.type="lowpass";
    lp.frequency.setValueAtTime(500,t);lp.frequency.exponentialRampToValueAtTime(1100,t+0.18);
    var g=c.createGain();g.gain.setValueAtTime(0.0001,t);g.gain.exponentialRampToValueAtTime(0.07,t+0.03);g.gain.exponentialRampToValueAtTime(0.0006,t+0.28);
    s.connect(lp).connect(g).connect(c.destination);s.start(t);s.stop(t+0.34); }
  function click(t){ var c=ac(); if(!c)return; [1568,2093,3136].forEach(function(f,i){ var o=c.createOscillator(),g=c.createGain();
    o.type="sine";o.frequency.value=f; var v=[0.10,0.07,0.045][i];
    g.gain.setValueAtTime(0.0001,t);g.gain.exponentialRampToValueAtTime(v,t+0.004);g.gain.exponentialRampToValueAtTime(0.0004,t+0.5);
    o.connect(g).connect(c.destination);o.start(t);o.stop(t+0.55); }); }
  function chord(t){ var c=ac(); if(!c)return; [220,277.18,329.63].forEach(function(f){ var o=c.createOscillator(),g=c.createGain();
    o.type="triangle";o.frequency.value=f;
    g.gain.setValueAtTime(0.0001,t);g.gain.linearRampToValueAtTime(0.05,t+0.14);g.gain.exponentialRampToValueAtTime(0.0004,t+0.75);
    o.connect(g).connect(c.destination);o.start(t);o.stop(t+0.8); }); }

  function playAnim(sound){
    var base=el("apxBase"),navy=el("apxNavy"),lite=el("apxLite"),eL=el("apxEdgeL"),eR=el("apxEdgeR"),
        flash=el("apxFlash"),logo=el("apxLogo"),sp=el("apiceSplash");
    if(!base||!sp)return;
    var word=sp.querySelector(".apx-word"), sub=sp.querySelector(".apx-sub");
    [eL,eR].forEach(function(e){e.style.strokeDasharray=EDGE;e.style.strokeDashoffset=EDGE;});
    if(word)word.classList.remove("on"); if(sub)sub.classList.remove("on");
    base.animate([{transform:"translateY(200px) scale(.94)",opacity:0},{transform:"none",opacity:1}],{delay:150,duration:660,easing:EASE,fill:"both"});
    navy.animate([{transform:"translateX(180px) rotate(10deg)",opacity:0},{transform:"none",opacity:1}],{delay:405,duration:615,easing:EASE,fill:"both"});
    lite.animate([{transform:"translateX(-185px)",opacity:0},{opacity:.85,transform:"none"}],{delay:630,duration:570,easing:EASE,fill:"both"});
    flash.animate([{transform:"scale(.15)",opacity:0,offset:0},{transform:"scale(1)",opacity:.95,offset:.35},{transform:"scale(2.6)",opacity:0,offset:1}],{delay:1350,duration:780,easing:"cubic-bezier(.2,.7,.3,1)",fill:"both"});
    var eo={delay:1395,duration:450,easing:"cubic-bezier(.3,.7,.2,1)",fill:"both"};
    eL.animate([{strokeDashoffset:EDGE,opacity:0,offset:0},{opacity:1,offset:.2},{strokeDashoffset:0,opacity:0,offset:1}],eo);
    eR.animate([{strokeDashoffset:EDGE,opacity:0,offset:0},{opacity:1,offset:.2},{strokeDashoffset:0,opacity:0,offset:1}],eo);
    logo.animate([{transform:"scale(1)",offset:0},{transform:"scale(1.05)",offset:.18},{transform:"scale(.995)",offset:.4},{transform:"scale(1)",offset:1}],{delay:1320,duration:1140,easing:"ease-out",fill:"both"});
    el("apiceSplash").querySelector(".apx-stage").animate([{filter:"drop-shadow(0 20px 50px rgba(0,0,0,.45)) brightness(1)"},{filter:"drop-shadow(0 20px 60px rgba(63,200,189,.35)) brightness(1.12)",offset:.25},{filter:"drop-shadow(0 20px 50px rgba(0,0,0,.45)) brightness(1)"}],{delay:1350,duration:1350,easing:"ease-in-out",fill:"both"});
    setTimeout(function(){if(word)word.classList.add("on");if(sub)sub.classList.add("on");},1620);
    if(sound){ var c=ac(); if(c){ var t=c.currentTime+0.02; whoosh(t+0.15);whoosh(t+0.405);whoosh(t+0.63);click(t+1.38);chord(t+1.42); } }
  }

  window.apiceIntro=function(sound){
    var sp=el("apiceSplash"); if(!sp||running)return; running=true;
    sp.style.display="flex"; sp.classList.remove("apx-hide");
    setTimeout(function(){ sp.classList.add("apx-hide"); setTimeout(function(){sp.style.display="none";running=false;},650); },2600);
    try{ playAnim(sound); }catch(e){}
  };

  // Al entrar: se muestra una vez por sesión del navegador (sin sonido: los navegadores lo bloquean sin gesto).
  if(!sessionStorage.getItem("apxSeen")){ sessionStorage.setItem("apxSeen","1"); if(document.readyState!=="loading")apiceIntro(false); else window.addEventListener("load",function(){apiceIntro(false);}); }
  // Tocar el logo (barra lateral / bienvenida / bloqueo) la repite con sonido.
  document.addEventListener("click",function(e){ var t=e.target; if(t&&t.closest&&t.closest(".sb-logo, .onb-logo, .lock-logo")){ apiceIntro(true);
    /* El logo de la barra, ademas de la animacion, vuelve al inicio (Mi dia).
       Los logos de login/bloqueo solo animan: ahi todavia no hay app cargada. */
    if(t.closest(".sb-logo") && typeof window.navTo==="function"){ window.navTo("dashboard"); }
  } });
})();
