// Renders public/social.png (1280×640): the social preview of the repository
// and the og:image of the site. Needs Google Chrome. Run: npm run social-image
import { execFileSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { groveSvg } from '../src/lib/grove.mjs';

const root = fileURLToPath(new URL('..', import.meta.url));
const font = (path) => pathToFileURL(join(root, 'node_modules', path)).href;

const chrome =
	process.env.CHROME_PATH ??
	(process.platform === 'darwin'
		? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
		: 'google-chrome');

const html = `<!doctype html>
<meta charset="utf-8">
<style>
@font-face { font-family: 'Young Serif'; src: url(${font('@fontsource/young-serif/files/young-serif-latin-400-normal.woff2')}); }
@font-face { font-family: 'Schibsted Grotesk'; font-weight: 400 900; src: url(${font('@fontsource-variable/schibsted-grotesk/files/schibsted-grotesk-latin-wght-normal.woff2')}); }
@font-face { font-family: 'IBM Plex Mono'; src: url(${font('@fontsource/ibm-plex-mono/files/ibm-plex-mono-latin-400-normal.woff2')}); }
* { margin: 0; box-sizing: border-box; }
html, body { width: 1280px; height: 640px; }
body {
	--grove-needle: #e3ad34;
	--grove-branch: #8d9b8a;
	--grove-broken: #e8836a;
	--grove-ink: #f1f3ec;
	--grove-ground: #131c18;
	--grove-label-font: 'Young Serif';
	background: #131c18;
	color: #f1f3ec;
	display: grid;
	grid-template-columns: 1fr 520px;
	align-items: center;
	gap: 40px;
	padding: 0 88px 0 96px;
	font-family: 'Schibsted Grotesk', sans-serif;
}
h1 { font-family: 'Young Serif'; font-weight: 400; font-size: 188px; line-height: 0.9; letter-spacing: -0.02em; }
p { margin-top: 28px; font-size: 31px; line-height: 1.35; color: #bfc9b9; max-width: 24ch; }
code { display: inline-block; margin-top: 40px; font-family: 'IBM Plex Mono'; font-size: 21px; color: #e3ad34; }
svg { width: 520px; height: 520px; display: block; }
</style>
<div>
	<h1>Larch</h1>
	<p>Architecture rules for Laravel apps, as a Pest plugin.</p>
	<code>composer require nwidart/larch --dev</code>
</div>
${groveSvg({ id: 'social' })}
`;

const dir = mkdtempSync(join(tmpdir(), 'larch-social-'));
const page = join(dir, 'social.html');
writeFileSync(page, html);

const out = join(root, 'public', 'social.png');
execFileSync(chrome, [
	'--headless=new',
	'--hide-scrollbars',
	'--force-device-scale-factor=1',
	'--window-size=1280,640',
	'--allow-file-access-from-files',
	`--screenshot=${out}`,
	pathToFileURL(page).href,
]);
console.log(`Wrote ${out}`);
