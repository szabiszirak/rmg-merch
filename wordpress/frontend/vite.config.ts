import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';
export default defineConfig({
 plugins:[react()],
 base:'./',
 build:{outDir:'../plugin/rmg-merch/assets/app',emptyOutDir:true,cssCodeSplit:true,
  rollupOptions:{input:{storefront:'src/storefront-main.tsx',admin:'src/admin-main.tsx'},output:{entryFileNames:'[name].js',chunkFileNames:'chunk-[hash].js',assetFileNames:(i:any)=>(i.names?.[0]||i.name||'').endsWith('.css')?'app.css':'[name][extname]'}}}
});
