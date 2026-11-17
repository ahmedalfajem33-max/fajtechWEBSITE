import fs from 'node:fs';
import path from 'node:path';

/**
 * Vendor partner badges. Put the OFFICIAL badge from each partner portal in public/partners/
 * named after `slug` (svg, png or webp), e.g. public/partners/fortinet.png.
 * A partner only appears on the site once its badge file exists.
 * Set `homepage: false` to keep a badge off the homepage strip (it still shows on the About page).
 */
export const partners = [
  { slug: 'cisco', name: 'Cisco', program: 'Cisco Partner', homepage: true },
  { slug: 'fortinet', name: 'Fortinet', program: 'Fortinet Engage Partner', homepage: true },
  { slug: 'dell', name: 'Dell Technologies', program: 'Dell Technologies Partner', homepage: true },
  { slug: 'kaspersky', name: 'Kaspersky', program: 'Kaspersky United Partner', homepage: true },
];

const dir = path.join(process.cwd(), 'public', 'partners');
const exts = ['svg', 'png', 'webp', 'jpg'];

/** Partners whose badge file is present, with the public path to the file. */
export function partnerBadges(opts: { homepage?: boolean } = {}) {
  return partners
    .filter((p) => (opts.homepage ? p.homepage : true))
    .map((p) => {
      const ext = exts.find((e) => fs.existsSync(path.join(dir, `${p.slug}.${e}`)));
      return ext ? { ...p, src: `/partners/${p.slug}.${ext}` } : null;
    })
    .filter((p): p is NonNullable<typeof p> => p !== null);
}
