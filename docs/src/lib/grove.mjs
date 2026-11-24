// Draws the Larch mark: modules as larch needle clusters, links as branches.
// One link breaks a rule and is drawn in bark red. The hero of the docs and
// the social image (scripts/social-image.mjs) both render this SVG.

const NODES = [
	// label: where the name sits, relative to the cluster, clear of the branches.
	{ id: 'billing', label: 'Billing', x: 112, y: 168, needles: 26, at: [0, -42] },
	{ id: 'inventory', label: 'Inventory', x: 352, y: 74, needles: 22, at: [0, -40] },
	{ id: 'catalog', label: 'Catalog', x: 378, y: 238, needles: 24, at: [0, 52] },
	{ id: 'shared', label: 'Shared', x: 214, y: 338, needles: 30, at: [0, 52] },
];

const LINKS = [
	{ from: 'billing', to: 'shared', bend: -0.18 },
	{ from: 'catalog', to: 'shared', bend: 0.16 },
	{ from: 'billing', to: 'catalog', bend: 0.14 },
	{ from: 'inventory', to: 'shared', bend: 0.22 },
	{ from: 'billing', to: 'inventory', bend: -0.2, broken: true },
];

// Small deterministic PRNG, so every build draws the same needles.
function random(seed) {
	let s = seed;
	return () => {
		s = (s * 1664525 + 1013904223) % 4294967296;
		return s / 4294967296;
	};
}

function needles(node, seed) {
	const next = random(seed);
	const lines = [];
	for (let i = 0; i < node.needles; i++) {
		const angle = (i / node.needles) * Math.PI * 2 + (next() - 0.5) * 0.35;
		const inner = 5 + next() * 2;
		const outer = 19 + next() * 13;
		const f = (n) => n.toFixed(1);
		lines.push(
			`<line x1="${f(node.x + Math.cos(angle) * inner)}" y1="${f(node.y + Math.sin(angle) * inner)}" x2="${f(node.x + Math.cos(angle) * outer)}" y2="${f(node.y + Math.sin(angle) * outer)}"/>`,
		);
	}
	return lines.join('');
}

function branch(link, byId) {
	const a = byId[link.from];
	const b = byId[link.to];
	const dx = b.x - a.x;
	const dy = b.y - a.y;
	const length = Math.hypot(dx, dy);
	// Start and stop clear of the needles.
	const gap = 38;
	const sx = a.x + (dx / length) * gap;
	const sy = a.y + (dy / length) * gap;
	const ex = b.x - (dx / length) * gap;
	const ey = b.y - (dy / length) * gap;
	const cx = (sx + ex) / 2 - dy * link.bend;
	const cy = (sy + ey) / 2 + dx * link.bend;
	const f = (n) => n.toFixed(1);
	return {
		d: `M${f(sx)} ${f(sy)} Q${f(cx)} ${f(cy)} ${f(ex)} ${f(ey)}`,
		mid: { x: (sx + 2 * cx + ex) / 4, y: (sy + 2 * cy + ey) / 4 },
	};
}

/**
 * @param {{ id?: string, title?: string }} [options]
 * @returns {string} SVG markup. Colors come from the CSS custom properties
 *   --grove-needle, --grove-branch, --grove-broken, --grove-ink and --grove-label-font.
 */
export function groveSvg({ id = 'grove', title = 'Four modules drawn as larch needle clusters. The link from Billing to Inventory breaks a rule.' } = {}) {
	const byId = Object.fromEntries(NODES.map((n) => [n.id, n]));
	const branches = LINKS.map((link) => {
		const { d, mid } = branch(link, byId);
		const cls = link.broken ? 'grove-broken' : 'grove-branch';
		const mark = link.broken
			? `<g class="grove-cross" transform="translate(${mid.x.toFixed(1)} ${mid.y.toFixed(1)})"><circle r="9"/><path d="M-4 -4 4 4M4 -4-4 4"/></g>`
			: '';
		return `<path class="${cls}" d="${d}" marker-end="url(#${id}-${link.broken ? 'tip-broken' : 'tip'})"/>${mark}`;
	});
	const clusters = NODES.map(
		(node, i) =>
			`<g class="grove-cluster"><g class="grove-needles">${needles(node, 7 + i * 31)}</g><circle class="grove-shoot" cx="${node.x}" cy="${node.y}" r="4.5"/><text x="${node.x + node.at[0]}" y="${node.y + node.at[1]}">${node.label}</text></g>`,
	);

	return `<svg class="grove" viewBox="40 0 400 400" role="img" aria-labelledby="${id}-title" xmlns="http://www.w3.org/2000/svg">
<title id="${id}-title">${title}</title>
<defs>
<marker id="${id}-tip" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 1 9 5 0 9" fill="none" stroke="var(--grove-branch)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></marker>
<marker id="${id}-tip-broken" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 1 9 5 0 9" fill="none" stroke="var(--grove-broken)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></marker>
</defs>
<style>
.grove .grove-branch,.grove .grove-broken{fill:none;stroke-width:1.6;stroke-linecap:round}
.grove .grove-branch{stroke:var(--grove-branch)}
.grove .grove-broken{stroke:var(--grove-broken);stroke-dasharray:5 5}
.grove .grove-cross circle{fill:var(--grove-ground)}
.grove .grove-cross circle,.grove .grove-cross path{stroke:var(--grove-broken);stroke-width:1.6;stroke-linecap:round}
.grove .grove-needles line{stroke:var(--grove-needle);stroke-width:1.5;stroke-linecap:round}
.grove .grove-shoot{fill:var(--grove-ink)}
.grove text{fill:var(--grove-ink);font-family:var(--grove-label-font);font-size:17px;text-anchor:middle}
</style>
${branches.join('\n')}
${clusters.join('\n')}
</svg>`;
}
