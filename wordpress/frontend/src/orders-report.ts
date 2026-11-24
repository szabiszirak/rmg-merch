import {apiFetch,PRICE} from './wp';
import {downloadXlsx,localTime} from './xlsx';

type Order={id:string;name:string;email:string;schoolClass:string;items:string;total:number;paid:number;collected:number;createdAt:string};
const sizes=['S','M','L','XL','XXL'],colors=['Fekete','Fehér'];

/** Rendelések Excel-táblája: Rendelések, Osztályok és Gyártás munkalap. */
export async function downloadReport(){
 const r=await apiFetch('/api/orders');if(!r.ok)throw Error(await r.text());const list:Order[]=(await r.json())||[];
 const items=list.flatMap(o=>JSON.parse(o.items).map((i:any)=>({...i,schoolClass:o.schoolClass})));
 const sum=(c:string,s:string,cl?:string)=>items.filter(i=>i.color===c&&i.size===s&&(!cl||i.schoolClass===cl)).reduce((n,i)=>n+i.qty,0);
 const classes=[...new Set(list.map(o=>o.schoolClass))].sort((a,b)=>a.localeCompare(b,'hu',{numeric:true}));
 downloadXlsx('RMG-rendelesek.xlsx',[
  {name:'Rendelések',rows:[['Azonosító','Név','E-mail','Osztály','Szín','Méret','Darab','Tétel összege (Ft)','Fizetve','Átadva','Rendelés ideje'],
   ...list.flatMap(o=>JSON.parse(o.items).map((i:any)=>[o.id,o.name,o.email,o.schoolClass,i.color,i.size,i.qty,i.qty*PRICE,o.paid?'Igen':'Nem',o.collected?'Igen':'Nem',localTime(o.createdAt)]))]},
  {name:'Osztályok',rows:[['Osztály','Szín',...sizes,'Összesen'],...classes.flatMap(cl=>colors.map(c=>[cl,c,...sizes.map(s=>sum(c,s,cl)),sizes.reduce((n,s)=>n+sum(c,s,cl),0)]))]},
  {name:'Gyártás',rows:[['Szín',...sizes,'Összesen'],...colors.map(c=>[c,...sizes.map(s=>sum(c,s)),sizes.reduce((n,s)=>n+sum(c,s),0)]),['Összesen',...sizes.map(s=>colors.reduce((n,c)=>n+sum(c,s),0)),items.reduce((n,i)=>n+i.qty,0)]]},
 ],{top:['RMG Merch – rendelések','Letöltve: '+localTime(new Date().toISOString())+' (budapesti idő)']});
}
