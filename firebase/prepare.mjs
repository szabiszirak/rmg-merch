import fs from 'node:fs';
fs.mkdirSync('src',{recursive:true});
let front=fs.readFileSync('../app/page.tsx','utf8').replace("'use client';","'use client';\nimport {apiFetch} from './api';").replaceAll("fetch('/api/","apiFetch('/api/");
let admin=fs.readFileSync('../app/admin/view.tsx','utf8').replace("'use client';","'use client';\nimport {apiFetch} from './api';\nimport {downloadReport} from './report';").replaceAll("fetch('/api/","apiFetch('/api/").replace('<a className="button dark" href="/api/report">Excel-kimutatás letöltése ↓</a>','<button className="button dark" onClick={()=>downloadReport().catch(e=>M(String(e)))}>Excel-kimutatás letöltése ↓</button>');
fs.writeFileSync('src/storefront.tsx',front);fs.writeFileSync('src/admin.tsx',admin);
for(const f of ['merch.css','redesign.css','typography.css'])fs.copyFileSync('../app/'+f,'src/'+f);
