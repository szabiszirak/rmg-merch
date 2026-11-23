import {apiFetch} from './wp';
type Account={name:string;email:string;schoolClass:string;registeredAt:string;orders:number;pieces:number};
const local=(s:string)=>new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Budapest',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false}).format(new Date(s));
const esc=(s:any)=>String(s).replace(/[<>&"']/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&apos;'}[c]!));

/** A regisztrált (Google-lel belépett) fiókok Excel-táblája: Fiókok és Osztályok munkalap. */
export async function downloadAccounts(){
 const r=await apiFetch('/api/accounts');if(!r.ok)throw Error(await r.text());const list:Account[]=await r.json();
 const classes=[...new Set(list.map(a=>a.schoolClass||'Nincs megadva'))].sort((a,b)=>a.localeCompare(b,'hu',{numeric:true}));
 const tables=[
  {name:'Fiókok',rows:[['Név','E-mail','Osztály','Regisztráció ideje','Rendelések','Rendelt pulóver (db)'],...list.map(a=>[a.name,a.email,a.schoolClass||'Nincs megadva',local(a.registeredAt),a.orders,a.pieces])]},
  {name:'Osztályok',rows:[['Osztály','Fiók','Rendelt már','Még nem rendelt'],...classes.map(cl=>{const g=list.filter(a=>(a.schoolClass||'Nincs megadva')===cl);const o=g.filter(a=>a.orders>0).length;return [cl,g.length,o,g.length-o]})]},
 ];
 const xml='<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'+tables.map(t=>'<Worksheet ss:Name="'+t.name+'"><Table>'+t.rows.map(row=>'<Row>'+row.map((cell:any)=>'<Cell><Data ss:Type="'+(typeof cell==='number'?'Number':'String')+'">'+esc(cell)+'</Data></Cell>').join('')+'</Row>').join('')+'</Table></Worksheet>').join('')+'</Workbook>';
 const url=URL.createObjectURL(new Blob([xml],{type:'application/vnd.ms-excel;charset=utf-8'}));const link=document.createElement('a');link.href=url;link.download='RMG-fiokok.xml';link.click();setTimeout(()=>URL.revokeObjectURL(url),30000);
}
