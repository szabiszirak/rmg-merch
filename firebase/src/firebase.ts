import {initializeApp} from 'firebase/app';
import {getAuth,GoogleAuthProvider,signInWithPopup,onAuthStateChanged} from 'firebase/auth';
import {getFirestore} from 'firebase/firestore';
let promise:ReturnType<typeof initialize>|undefined;
async function initialize(){const r=await fetch('/firebase-config.json',{cache:'no-store'});if(!r.ok||!r.headers.get('content-type')?.includes('json'))throw Error('A Firebase-projekt még nincs csatlakoztatva.');const c=await r.json();if(!c.apiKey||!c.projectId||!c.adminEmail)throw Error('Hiányos Firebase-beállítás.');const app=initializeApp(c);const auth=getAuth(app);const db=getFirestore(app);await new Promise<void>(resolve=>{const unsub=onAuthStateChanged(auth,()=>{unsub();resolve()})});return {auth,db,adminEmail:c.adminEmail as string}}
export function firebase(){return promise??=initialize()}
export async function login(){const {auth}=await firebase();return signInWithPopup(auth,new GoogleAuthProvider())}
