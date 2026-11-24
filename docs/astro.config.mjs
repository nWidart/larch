// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// Served by GitHub Pages at https://nwidart.github.io/larch/.
export default defineConfig({
	site: 'https://nwidart.github.io',
	base: '/larch',
	integrations: [
		starlight({
			title: 'Larch',
			description:
				'Architecture rules for Laravel apps, as a Pest plugin. Larch sees the links that facades, the container, events and jobs make.',
			social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/nwidart/larch' }],
			editLink: {
				baseUrl: 'https://github.com/nwidart/larch/edit/main/docs/',
			},
			lastUpdated: true,
			// One group per reader need (Diátaxis): learn, do, look up, understand.
			sidebar: [
				{
					label: 'Start here',
					items: [{ label: 'Getting started', slug: 'getting-started' }],
				},
				{
					label: 'Guides',
					items: [
						'guides/define-modules',
						'guides/module-boundaries',
						'guides/runtime-links',
						'guides/app-rules',
						'guides/baseline',
						'guides/mortise',
						'guides/graph-export',
					],
				},
				{
					label: 'Reference',
					items: [
						'reference/configuration',
						'reference/subjects',
						'reference/module-rules',
						'reference/app-rules',
						'reference/link-kinds',
						'reference/failure-messages',
						'reference/baseline',
						'reference/mortise',
						'reference/graph-command',
					],
				},
				{
					label: 'Concepts',
					items: ['concepts/modules', 'concepts/reading-the-app', 'concepts/baseline'],
				},
			],
		}),
	],
});
