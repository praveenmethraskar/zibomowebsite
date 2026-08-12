# zibomowebsite

Static marketing site for **Zibomo Smart Lockers** — a rebuild of [zibomo.ooo](https://www.zibomo.ooo/)
with the same content and information architecture, and a new visual execution.

## Running it

No build step. Open `index.html` in a browser, or serve the folder:

```bash
python3 -m http.server 8000
```

Bootstrap, Tailwind, jQuery and Bootstrap Icons load from CDN, so an internet
connection is needed on first load. Images and video are local.

## Structure

```
index.html          markup, one commented block per section
css/style.css       brand tokens + every animated / JS-toggled state
js/main.js          15 numbered interaction modules
assets/images/      photography, logos, partner marks
assets/video/       product video used by the "Who we are" modal
```

## Frameworks

Three layers share the page, with a strict division of labour so they cannot collide:

| Layer | Owns |
| --- | --- |
| **Bootstrap 5** | grid, navbar, carousel, tabs, modal, form controls |
| **Tailwind CSS** | fine-grained utility styling, `tw-` prefixed |
| **`css/style.css`** | brand tokens, bespoke components, anything animated |

Tailwind runs with `prefix: 'tw-'` so no utility can ever share a name with a
Bootstrap class, and with `corePlugins: { preflight: false }` so its base reset
cannot overwrite Bootstrap's Reboot. The few base defaults Tailwind's own border
utilities depend on are restored in section 01 of `css/style.css`.

Note that with a prefix, responsive variants are written `md:tw-flex` — *not*
`tw-md:flex`, which compiles to nothing.

## Sections

Home / hero · Use Cases · Who We Are · Features & Benefits · Product ·
How It Works · Collaboration · Clients · Contact · Footer

## Behaviour worth knowing

- **Scroll reveals** are gated behind a `.js` class set before first paint, and the
  whole of `main.js` is wrapped so that any exception removes that class — a broken
  script degrades to a full static page rather than a blank one.
- **`prefers-reduced-motion`** is honoured throughout.
- **The contact form has no backend.** It validates client-side and says so plainly
  instead of implying a message was sent. To wire it up, POST from the marked hook in
  module 15 of `js/main.js`.
