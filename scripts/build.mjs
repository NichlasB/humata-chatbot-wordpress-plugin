import fs from 'node:fs';
import path from 'node:path';
import { build, context } from 'esbuild';

const isWatch = process.argv.includes('--watch');

const repoRoot = process.cwd();
const assetsDir = path.join(repoRoot, 'assets');
const srcDir = path.join(assetsDir, 'src');
const distDir = path.join(assetsDir, 'dist');

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function fileExists(p) {
  try {
    fs.accessSync(p, fs.constants.F_OK);
    return true;
  } catch {
    return false;
  }
}

function existingEntries(map) {
  const out = {};
  for (const [name, p] of Object.entries(map)) {
    if (fileExists(p)) {
      out[name] = p;
    }
  }
  return out;
}

const jsEntryCandidates = {
  'chat-widget': path.join(srcDir, 'chat-widget', 'index.js'),
  'floating-help': path.join(srcDir, 'floating-help', 'index.js'),
  'admin-settings': path.join(srcDir, 'admin', 'settings', 'index.js')
};

const cssEntryCandidates = {
  'chat-widget': path.join(srcDir, 'chat-widget', 'style.css'),
  'floating-help': path.join(srcDir, 'floating-help', 'style.css'),
  'admin-settings': path.join(srcDir, 'admin', 'settings', 'style.css')
};

const jsEntries = existingEntries(jsEntryCandidates);
const cssEntries = existingEntries(cssEntryCandidates);

const common = {
  logLevel: 'info'
};

const jsBuildOptions = {
  ...common,
  entryPoints: jsEntries,
  outdir: distDir,
  entryNames: '[name]',
  bundle: true,
  platform: 'browser',
  format: 'iife',
  target: ['es2017'],
  sourcemap: true,
  minify: !isWatch
};

const cssBuildOptions = {
  ...common,
  entryPoints: cssEntries,
  outdir: distDir,
  entryNames: '[name]',
  bundle: true,
  platform: 'browser',
  sourcemap: true,
  minify: !isWatch
};

async function run() {
  ensureDir(distDir);

  if (Object.keys(jsEntries).length === 0 && Object.keys(cssEntries).length === 0) {
    console.log('[humata-assets] No entrypoints found under assets/src/. Nothing to build.');
    return;
  }

  if (isWatch) {
    console.log('[humata-assets] Starting watch...');
    const ctxs = [];

    if (Object.keys(jsEntries).length) {
      ctxs.push(await context(jsBuildOptions));
    }
    if (Object.keys(cssEntries).length) {
      ctxs.push(await context(cssBuildOptions));
    }

    for (const ctx of ctxs) {
      await ctx.watch();
    }

    console.log('[humata-assets] Watching for changes...');
    return;
  }

  console.log('[humata-assets] Building...');
  if (Object.keys(jsEntries).length) {
    await build(jsBuildOptions);
  }
  if (Object.keys(cssEntries).length) {
    await build(cssBuildOptions);
  }
  console.log('[humata-assets] Build complete.');
}

run().catch((err) => {
  console.error('[humata-assets] Build failed:', err);
  process.exit(1);
});















