import {apiFetch} from './wp';
import {downloadXlsx,localTime} from './xlsx';

type Account={name:string;email:string;schoolClass:string;registeredAt:string;orders:number;pieces:number};

/** A regisztrált (Google-lel belépett) fiókok Excel-táblája: Fiókok és Osztályok munkalap. */
export async function downloadAccounts(){
 const r=await apiFetch('/api/accounts');if(!r.ok)throw Error(await r.text());const list:Account[]=(await r.json())||[];
 const cls=(a:Account)=>a.schoolClass||'Nincs megadva';
 const classes=[...new Set(list.map(cls))].sort((a,b)=>a.localeCompare(b,'hu',{numeric:true}));
 downloadXlsx('RMG-fiokok.xlsx',[
  {name:'Fiókok',rows:[['Név','E-mail','Osztály','Regisztráció ideje','Rendelések','Rendelt pulóver (db)'],...list.map(a=>[a.name,a.email,cls(a),localTime(a.registeredAt),a.orders,a.pieces])]},
  {name:'Osztályok',rows:[['Osztály','Fiók','Rendelt már','Még nem rendelt'],...classes.map(cl=>{const g=list.filter(a=>cls(a)===cl);const o=g.filter(a=>a.orders>0).length;return [cl,g.length,o,g.length-o]})]},
 ],{top:['RMG Merch – regisztrált fiókok','Letöltve: '+localTime(new Date().toISOString())+' (budapesti idő)']});
}
