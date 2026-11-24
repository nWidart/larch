# Larch documentation

The documentation site of Larch, built with [Starlight](https://starlight.astro.build).
GitHub Pages serves it at https://nwidart.github.io/larch/.

```bash
npm install
npm run dev      # http://localhost:4321/larch/
npm run build    # writes dist/
```

Pages live in `src/content/docs`. The sidebar is in `astro.config.mjs`.

The theme is in `src/styles/larch.css`. The module graph of the home page comes from `src/lib/grove.mjs`.
`npm run social-image` draws the same graph into `public/social.png` with Google Chrome. The site uses
that image as its `og:image`, and the README as its banner. Upload it again as the social preview
in the repository settings on GitHub after you change it.

## Where a page goes

The site follows [Diátaxis](https://diataxis.fr/). Each page does one job:

| Folder | Type | Reader | Write |
|---|---|---|---|
| `getting-started.mdx` | Tutorial | Learning Larch | One path that always works, with a result at each step. |
| `guides/` | How-to guide | Has a goal | "To get X, do Y." Start and end at the goal. No theory. |
| `reference/` | Reference | Looks something up | Each method, option and field, in the same form. No advice. |
| `concepts/` | Explanation | Wants to understand | Why Larch works as it does, and the trade-offs. |

A change to Larch that users see updates the reference page of what it changed, and the guide that uses it.
