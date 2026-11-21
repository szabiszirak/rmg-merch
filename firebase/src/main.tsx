import React,{useEffect,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {onAuthStateChanged,signOut,type User} from 'firebase/auth';
import {firebase,login} from './firebase';
import Storefront from './storefront';
import Admin from './admin';
import './merch.css';import './redesign.css';import './typography.css';
function AdminGate(){const [user,U]=useState<User|null>(null),[ready,R]=useState(false),[email,A]=useState(''),[error,E]=useState('');useEffect(()=>{let unsub:undefined|(()=>void);firebase().then(({auth,adminEmail})=>{A(adminEmail);unsub=onAuthStateChanged(auth,u=>{U(u);R(true)})}).catch(e=>{E(e.message);R(true)});return()=>unsub?.()},[]);async function signin(){try{E('');await login()}catch(e){E(e instanceof Error?e.message:String(e))}}if(ready&&user?.emailVerified&&user.email?.toLowerCase()===email.toLowerCase())return <><div className="firebase-auth-bar"><span>{user.email}</span><button onClick={async()=>{const {auth}=await firebase();await signOut(auth)}}>Kijelentkezés</button></div><Admin/></>;return <main className="denied"><a href="/">← Vissza a pulóverekhez</a><div className="eyebrow" style={{marginTop:50}}>SZERVEZŐI BELÉPÉS</div><h1>Kezelőfelület</h1><p>A rendeléseket csak az engedélyezett Google-fiókkal lehet kezelni.</p>{ready&&<button className="button dark" onClick={signin}>Belépés Google-fiókkal ↗</button>}{user?.email&&<p>Jelenlegi fiók: {user.email}</p>}{error&&<p className="error" role="alert">{error}</p>}</main>}
createRoot(document.getElementById('root')!).render(location.pathname.startsWith('/admin')?<AdminGate/>:<Storefront/>);
