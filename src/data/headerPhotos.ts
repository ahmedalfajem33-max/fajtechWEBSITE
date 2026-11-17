/**
 * Header photos live in src/assets/headers/, named by page key, e.g.:
 *   home.jpg, services.jpg, service-cybersecurity.jpg, industries.jpg, industry-healthcare.jpg,
 *   location-calgary.jpg, about.jpg, contact.jpg, support.jpg, resources.jpg, assessment.jpg,
 *   booking.jpg, privacy.jpg, article-<slug>.jpg, location-<slug>.jpg, default.jpg
 * Each page uses its own photo if present, then its section photo, then default.jpg.
 * Alt text (for accessibility and Google Images) is set here.
 */
const alts: Record<string, string> = {
  home: 'FAJTech IT team supporting a business in Calgary',
  services: 'IT professional managing business systems',
  industries: 'Business teams supported by FAJTech managed IT',
  about: 'The FAJTech team',
  contact: 'Talk to the FAJTech team',
  support: 'FAJTech help desk technician supporting a client',
  resources: 'IT and cybersecurity guides',
  assessment: 'IT and cybersecurity assessment',
  booking: 'Free IT consultation with FAJTech',
  privacy: 'Protecting your personal information',
  default: 'FAJTech managed IT and cybersecurity',
};
export function headerAlt(key: string): string {
  if (alts[key]) return alts[key];
  if (key.startsWith('service-')) return `${key.slice(8).replace(/-/g, ' ')} services by FAJTech`;
  if (key.startsWith('industry-')) return `IT services for ${key.slice(9).replace(/-/g, ' ')}`;
  if (key.startsWith('article-')) return `${key.slice(8).replace(/-/g, ' ')}: IT guide from FAJTech`;
  if (key.startsWith('location-')) return `IT support in ${key.slice(9).replace(/-/g, ' ')}`;
  return alts.default;
}
