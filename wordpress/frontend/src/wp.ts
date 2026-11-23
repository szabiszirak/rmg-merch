import {createElement,useEffect,useRef,useState} from 'react';

type Config={rest:string;nonce:string;ajax:string;googleClientId:string;price:number;contactEmail:string;privacyUrl:string;assets:string;home:string};
declare global{interface Window{RMG?:Partial<Config>;google?:any}}
const cfg=():Partial<Config>=>window.RMG||{};
let nonce=cfg().nonce||'';

export const PRICE=Number(cfg().price)||10000;
export const PRICE_TEXT=PRICE.toLocaleString('hu-HU');
export const asset=(file:string)=>(cfg().assets||'/')+file;

async function freshNonce(){try{const r=await fetch((cfg().ajax||'/wp-admin/admin-ajax.php')+'?action=rest-nonce',{credentials:'same-origin'});const t=(await r.text()).trim();nonce=r.ok&&t&&t!=='0'?t:''}catch{nonce=''}}

/** A felület '/api/...' hívásait a WordPress REST API-ra (rmg/v1) irányítja. */
export async function apiFetch(path:string,init:RequestInit={}):Promise<Response>{
 const url=(cfg().rest||'/wp-json/rmg/v1/')+path.replace(/^\/api\//,'');
 const send=()=>{const h=new Headers(init.headers);if(nonce)h.set('X-WP-Nonce',nonce);else h.delete('X-WP-Nonce');if(init.body&&!h.has('Content-Type'))h.set('Content-Type','application/json');return fetch(url,{...init,headers:h,credentials:'same-origin'})};
 let r:Response;
 try{r=await send()}catch{return new Response('A kapcsolat megszakadt. Próbáld újra; a rendelésed nem kerül be kétszer.',{status:503})}
 if(r.status===403){const j=await r.clone().json().catch(()=>null);if(j?.code==='rest_cookie_invalid_nonce'){await freshNonce();r=await send()}}
 const text=await r.text();let data:any=null;try{data=text?JSON.parse(text):null}catch{}
 if(!r.ok)return new Response(data?.message||'Hiba történt. Próbáld újra.',{status:r.status});
 if(path==='/api/session'&&data&&'nonce' in data){nonce=data.nonce||'';return Response.json(data.user)}
 return new Response(text,{status:r.status,headers:{'Content-Type':'application/json'}});
}

let gsi:Promise<any>|undefined;let handler:(c:string)=>void=()=>{};let initialized=false;
function loadGoogle(){return gsi??=new Promise((resolve,reject)=>{if(window.google?.accounts?.id)return resolve(window.google);const s=document.createElement('script');s.src='https://accounts.google.com/gsi/client';s.async=true;s.onload=()=>window.google?.accounts?.id?resolve(window.google):reject(Error('A Google-belépés nem töltődött be.'));s.onerror=()=>{gsi=undefined;reject(Error('A Google-belépés nem érhető el. Ellenőrizd a kapcsolatot, vagy kapcsold ki a reklámblokkolót.'))};document.head.appendChild(s)})}

/** A Google hivatalos „Bejelentkezés Google-fiókkal” gombja. */
export function GoogleLogin({onCredential,disabled}:{onCredential:(credential:string)=>void;disabled?:boolean}){
 const ref=useRef<HTMLDivElement>(null);const [error,setError]=useState('');
 handler=onCredential;
 useEffect(()=>{let alive=true;const id=cfg().googleClientId;if(!id){setError('A Google-belépés még nincs beállítva. Szólj a szervezőnek.');return}
  loadGoogle().then(g=>{if(!alive||!ref.current)return;if(!initialized){g.accounts.id.initialize({client_id:id,callback:(r:any)=>r?.credential&&handler(r.credential),ux_mode:'popup',auto_select:false,itp_support:true,use_fedcm_for_button:true});initialized=true}
   ref.current.innerHTML='';g.accounts.id.renderButton(ref.current,{type:'standard',theme:'filled_black',size:'large',text:'signin_with',shape:'rectangular',logo_alignment:'left',locale:'hu',width:Math.max(200,Math.min(400,ref.current.clientWidth||320))})}).catch(e=>alive&&setError(e.message));
  return()=>{alive=false}},[]);
 return createElement('div',{className:'google-login'+(disabled?' is-busy':'')},createElement('div',{ref,className:'google-login-button'}),error?createElement('p',{className:'error',role:'alert'},error):null);
}
