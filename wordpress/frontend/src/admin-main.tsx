import {useEffect,useState} from 'react';
import {createRoot} from 'react-dom/client';
import Admin from './admin';
import {apiFetch,GoogleLogin} from './wp';
import './merch.css';import './redesign.css';import './typography.css';import './wp.css';
function Gate(){
 const [user,U]=useState<any>(undefined),[error,E]=useState(''),[busy,W]=useState(false);
 useEffect(()=>{apiFetch('/api/session').then(r=>r.ok?r.json():null).then(U).catch(()=>U(null))},[]);
 async function signin(credential:string){W(true);E('');try{const r=await apiFetch('/api/session',{method:'POST',body:JSON.stringify({credential})});if(!r.ok)throw Error(await r.text());U(await r.json())}catch(e){E(e instanceof Error?e.message:String(e))}finally{W(false)}}
 async function signout(){await apiFetch('/api/session',{method:'DELETE'});U(null)}
 if(user?.admin)return <><div className="firebase-auth-bar"><span>{user.email}</span><a href={(window.RMG as any)?.adminUrl||'/wp-admin/'}>WordPress admin</a><button onClick={signout}>Kijelentkezés</button></div><Admin/></>;
 return <main className="denied"><a href="/">← Vissza a pulóverekhez</a><div className="eyebrow" style={{marginTop:50}}>Szervezői belépés</div><h1>Kezelőfelület</h1><p>A rendeléseket csak szervezői jogosultságú fiókkal lehet kezelni.</p>{user===undefined?null:<GoogleLogin disabled={busy} onCredential={signin}/>}{user&&!user.admin&&<p>Jelenlegi fiók: {user.email} – ennek nincs szervezői jogosultsága.</p>}<p className="small-print"><a href={(window.RMG as any)?.loginUrl||'/wp-login.php'}>Belépés WordPress-jelszóval</a></p>{error&&<p className="error" role="alert">{error}</p>}</main>;
}
createRoot(document.getElementById('rmg-root')!).render(<Gate/>);
