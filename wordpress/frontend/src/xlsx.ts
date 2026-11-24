// Valódi Excel (.xlsx) fájl készítése a böngészőben, szerver nélkül.
import {zipSync,strToU8} from 'fflate';

export type Cell=string|number;
export type Sheet={name:string;rows:Cell[][];widths?:number[]};

const esc=(s:string)=>s.replace(/[<>&"]/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]!)).replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g,'');
const col=(i:number)=>{let s='';for(i++;i>0;i=Math.floor((i-1)/26))s=String.fromCharCode(65+((i-1)%26))+s;return s};
const sheetName=(s:string)=>esc(s.replace(/[\\/?*[\]:]/g,' ').slice(0,31));

function sheetXml(sh:Sheet){
 const cols=sh.rows.reduce((n,r)=>Math.max(n,r.length),0);
 const widths=Array.from({length:cols},(_,i)=>sh.widths?.[i]??Math.min(45,Math.max(8,...sh.rows.map(r=>String(r[i]??'').length+2))));
 const rows=sh.rows.map((r,ri)=>'<row r="'+(ri+1)+'">'+r.map((v,ci)=>{const ref=col(ci)+(ri+1);const st=ri===0?' s="1"':typeof v==='number'?' s="2"':'';
  return typeof v==='number'&&Number.isFinite(v)?'<c r="'+ref+'"'+st+'><v>'+v+'</v></c>':'<c r="'+ref+'"'+st+' t="inlineStr"><is><t xml:space="preserve">'+esc(String(v))+'</t></is></c>'}).join('')+'</row>').join('');
 const last=col(Math.max(cols,1)-1)+Math.max(sh.rows.length,1);
 return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
  +'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
  +'<cols>'+widths.map((w,i)=>'<col min="'+(i+1)+'" max="'+(i+1)+'" width="'+w+'" customWidth="1"/>').join('')+'</cols>'
  +'<sheetData>'+rows+'</sheetData>'+(sh.rows.length>1?'<autoFilter ref="A1:'+last+'"/>':'')+'</worksheet>';
}

export function xlsxBytes(sheets:Sheet[]):Uint8Array{
 const f:Record<string,Uint8Array>={};
 f['[Content_Types].xml']=strToU8('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'+sheets.map((_,i)=>'<Override PartName="/xl/worksheets/sheet'+(i+1)+'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>').join('')+'</Types>');
 f['_rels/.rels']=strToU8('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
 f['xl/workbook.xml']=strToU8('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'+sheets.map((s,i)=>'<sheet name="'+sheetName(s.name)+'" sheetId="'+(i+1)+'" r:id="rId'+(i+1)+'"/>').join('')+'</sheets>'+'<definedNames>'+sheets.map((s,i)=>s.rows.length>1?'<definedName name="_xlnm._FilterDatabase" localSheetId="'+i+'" hidden="1">\''+sheetName(s.name)+'\'!$A$1:$'+col(Math.max(1,s.rows.reduce((n,r)=>Math.max(n,r.length),0))-1)+'$'+s.rows.length+'</definedName>':'').join('')+'</definedNames></workbook>');
 f['xl/_rels/workbook.xml.rels']=strToU8('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'+sheets.map((_,i)=>'<Relationship Id="rId'+(i+1)+'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'+(i+1)+'.xml"/>').join('')+'<Relationship Id="rId'+(sheets.length+1)+'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
 f['xl/styles.xml']=strToU8('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF171918"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
 sheets.forEach((s,i)=>{f['xl/worksheets/sheet'+(i+1)+'.xml']=strToU8(sheetXml(s))});
 return zipSync(f,{level:6});
}

export function downloadXlsx(filename:string,sheets:Sheet[]){
 const url=URL.createObjectURL(new Blob([xlsxBytes(sheets) as BlobPart],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}));
 const a=document.createElement('a');a.href=url;a.download=filename;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),30000);
}

/** Dátum budapesti idő szerint, Excelben is olvasható formában: 2026-11-24 14:25. */
export const localTime=(s:string)=>new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Budapest',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false}).format(new Date(s));
