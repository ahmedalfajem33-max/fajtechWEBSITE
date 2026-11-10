export type Service = {
  slug: string;
  title: string;
  short: string;
  icon: string;
  intro: string;
  features: { title: string; text: string }[];
  outcomes: string[];
  featured?: boolean;
};

export const services: Service[] = [
  {
    slug: 'managed-it',
    title: 'Managed IT Services',
    short: 'Fully managed IT support with monitoring, proactive maintenance, and predictable pricing.',
    icon: 'server',
    featured: true,
    intro:
      'Everything your business needs to stay secure, supported and productive — for one predictable monthly fee. We monitor, patch and support your systems so problems are fixed before your team notices them.',
    features: [
      { title: '24/7 monitoring', text: 'Real-time monitoring of servers, workstations and network devices to prevent downtime before it happens.' },
      { title: 'Help desk support', text: 'Canadian IT experts ready to help your staff by phone, email or remote session whenever problems arise.' },
      { title: 'Proactive maintenance', text: 'Patching, updates, optimisation and routine health checks on a set schedule.' },
      { title: 'Vendor management', text: 'We deal with your ISP, software vendors and phone provider so you do not have to.' },
      { title: 'Onboarding & offboarding', text: 'New hires get working devices and accounts on day one; departing staff lose access the same day.' },
      { title: 'Asset & licence tracking', text: 'Always know what hardware and software you own, when warranties expire and what renewals are coming.' },
    ],
    outcomes: ['Faster issue resolution', 'Better staff productivity', 'Improved security posture', 'Reduced internal IT overhead'],
  },
  {
    slug: 'cybersecurity',
    title: 'Cybersecurity',
    short: 'Protection from threats with advanced security monitoring and compliance tools.',
    icon: 'shield',
    featured: true,
    intro:
      'We protect your business with multi-layered security built for modern threats — from endpoint protection and email filtering to staff training and incident response.',
    features: [
      { title: 'Managed detection & response', text: 'Real-time detection and response to suspicious activity across your entire environment.' },
      { title: 'Security awareness training', text: 'Phishing simulations and short training that help employees spot and avoid cyber threats.' },
      { title: 'Data protection', text: 'Encryption, access controls and backups that keep sensitive information safe from ransomware and leaks.' },
      { title: 'Email security', text: 'Advanced filtering, impersonation protection and DMARC/SPF/DKIM set up correctly.' },
      { title: 'Multi-factor authentication', text: 'MFA rolled out across Microsoft 365, VPN and critical apps without disrupting your team.' },
      { title: 'Compliance support', text: 'Controls and documentation aligned with PIPEDA, Alberta PIPA and your industry’s requirements.' },
    ],
    outcomes: ['Improved threat detection', 'Reduced likelihood of breaches', 'Greater employee awareness', 'Enhanced security posture'],
  },
  {
    slug: 'cloud-services',
    title: 'Cloud Services',
    short: 'Cloud migration, optimisation and a modern infrastructure built for growth.',
    icon: 'cloud',
    featured: true,
    intro:
      'From lift-and-shift migrations to cloud-native platforms, we build resilient, secure and cost-optimised cloud environments on Microsoft 365, Azure and AWS — with Canadian data residency where you need it.',
    features: [
      { title: 'Microsoft 365', text: 'Migration, licensing, Teams, SharePoint and OneDrive set up properly and secured.' },
      { title: 'Azure & AWS', text: 'Architecture, migration and management of servers and workloads in the cloud.' },
      { title: 'Cost governance', text: 'Right-size your environment and reduce wasted spend with ongoing cost reviews.' },
      { title: 'High availability', text: 'Architected for redundancy, automated backups and fast recovery.' },
      { title: 'Secure by default', text: 'Identity, encryption, network segmentation and compliance controls from day one.' },
      { title: 'Canadian data residency', text: 'Keep your data in Canadian regions when regulation or clients require it.' },
    ],
    outcomes: ['Lower infrastructure costs', 'Work securely from anywhere', 'Improved reliability', 'Scales as you grow'],
  },
  {
    slug: 'backup-disaster-recovery',
    title: 'Backup & Disaster Recovery',
    short: 'Recover quickly from outages with strong backup systems and business continuity planning.',
    icon: 'refresh',
    featured: true,
    intro:
      'Ransomware, hardware failure, floods, human error — when something goes wrong, you need your data and systems back fast. We design, monitor and regularly test backups so recovery is a plan, not a hope.',
    features: [
      { title: 'Automated backups', text: 'Servers, workstations, Microsoft 365 and cloud apps backed up automatically.' },
      { title: 'Offsite & immutable copies', text: 'Copies stored in Canadian data centres that ransomware cannot alter or delete.' },
      { title: 'Tested restores', text: 'We test restores on a schedule and report the results to you.' },
      { title: 'Business continuity plan', text: 'A documented plan for who does what when systems go down.' },
    ],
    outcomes: ['Minimal downtime', 'Protection from ransomware', 'Confidence your data is safe', 'Meets insurer requirements'],
  },
  {
    slug: 'network-security',
    title: 'Network Infrastructure & Security',
    short: 'Secure, fast and reliable networks for your office and remote teams.',
    icon: 'network',
    featured: true,
    intro:
      'Firewalls, Wi-Fi, switching and VPN designed for performance and protection. We build networks that stay up, stay fast and keep intruders out.',
    features: [
      { title: 'Firewall management', text: 'Next-generation firewalls configured, monitored and kept up to date.' },
      { title: 'Business Wi-Fi', text: 'Secure, segmented wireless with separate guest access.' },
      { title: 'Secure remote access', text: 'VPN and zero-trust access for staff working from anywhere.' },
      { title: 'Cabling & installs', text: 'Office moves, new sites and structured cabling handled end to end.' },
    ],
    outcomes: ['Fewer outages', 'Faster connections', 'Segmented, safer network', 'One partner for every site'],
  },
  {
    slug: 'it-consulting-vcio',
    title: 'IT Consulting & vCIO',
    short: 'Strategic IT guidance and fractional CIO leadership aligned with your business goals.',
    icon: 'compass',
    featured: true,
    intro:
      'Get senior technology leadership without a full-time salary. Our virtual CIO helps you budget, plan and make technology decisions that support where your business is going.',
    features: [
      { title: 'Technology roadmap', text: 'A clear 12–36 month plan tied to your business goals.' },
      { title: 'IT budgeting', text: 'Predictable spending with no surprise hardware or licence costs.' },
      { title: 'Quarterly business reviews', text: 'Regular reviews of risks, projects and opportunities.' },
      { title: 'Project leadership', text: 'Office moves, system replacements and migrations planned and delivered.' },
    ],
    outcomes: ['Technology that supports growth', 'Predictable IT budget', 'Better decisions, less risk', 'Executive-level guidance'],
  },
  {
    slug: 'hardware-as-a-service',
    title: 'Hardware as a Service',
    short: 'Enterprise hardware with flexible monthly pricing and full lifecycle management.',
    icon: 'laptop',
    intro:
      'All the hardware your business needs — laptops, desktops, servers, network gear — fully managed, upgraded and supported for a monthly fee. No large capital purchases, no outdated equipment.',
    features: [
      { title: 'Zero capital expenditure', text: 'Turn large hardware purchases into a predictable monthly cost.' },
      { title: 'Always current', text: 'Scheduled refresh cycles keep your team on modern, supported devices.' },
      { title: 'Full lifecycle', text: 'Procurement, setup, support, and secure data wiping at end of life.' },
    ],
    outcomes: ['Zero capital expenditure', 'Always up-to-date hardware', 'Lower operational risk', 'Improved reliability'],
  },
  {
    slug: 'automation',
    title: 'Business Automation',
    short: 'Automate manual processes and increase efficiency with RPA and workflow tools.',
    icon: 'bolt',
    intro:
      'Stop copying data between systems by hand. We automate repetitive work with Power Automate, Zoho, RPA and integrations between the tools you already use.',
    features: [
      { title: 'Process review', text: 'We find the manual, repetitive tasks that cost your team the most time.' },
      { title: 'Workflow automation', text: 'Approvals, notifications and data entry automated across your apps.' },
      { title: 'System integration', text: 'Connect your CRM, accounting, email and line-of-business software.' },
    ],
    outcomes: ['Hours saved every week', 'Fewer data-entry errors', 'Faster turnaround', 'Happier staff'],
  },
];

export const industries = [
  { title: 'Healthcare & Medical Practices', text: 'Secure, compliant IT that supports patient care, clinic operations and health-information privacy.', icon: 'heart' },
  { title: 'Financial Services & Accounting', text: 'Robust infrastructure and cybersecurity for firms that handle sensitive client financial data.', icon: 'chart' },
  { title: 'Legal', text: 'Data security, compliance and workflow optimisation for law firms.', icon: 'scale' },
  { title: 'Construction & Trades', text: 'Reliable connectivity, mobile devices and cloud tools for teams on job sites and in the office.', icon: 'hardhat' },
  { title: 'Oil, Gas & Energy', text: 'Mission-critical infrastructure built for safety, compliance and operational efficiency.', icon: 'flame' },
  { title: 'Manufacturing & Logistics', text: 'Automation, IoT integration and dependable IT for production environments.', icon: 'factory' },
  { title: 'Retail & eCommerce', text: 'Point-of-sale support, cloud systems and secure networks for retail operations.', icon: 'cart' },
  { title: 'Real Estate & Property Management', text: 'Flexible, secure IT that supports collaboration, data protection and growth.', icon: 'building' },
  { title: 'Non-Profits', text: 'Cost-effective IT that helps non-profits focus resources on impact, not infrastructure.', icon: 'hand' },
  { title: 'Education & Training', text: 'Reliable IT for schools, institutions, online learning and student management.', icon: 'book' },
  { title: 'Local Government', text: 'Secure, scalable systems that support public services and community operations.', icon: 'landmark' },
  { title: 'Automotive & Dealerships', text: 'Technology that keeps dealerships, service centres and operations running smoothly.', icon: 'car' },
];

export const faqs = [
  {
    q: 'What does FAJTech do?',
    a: 'FAJTech is a Calgary-based managed IT services provider. We look after your computers, network, cloud systems and cybersecurity for a predictable monthly fee, and we also deliver one-off IT projects such as Microsoft 365 migrations, office moves and network upgrades.',
  },
  {
    q: 'Where is FAJTech located, and do you serve businesses outside Calgary?',
    a: 'We are based in Calgary, Alberta and provide on-site support across the Calgary area. Most support is delivered remotely, so we serve businesses across Canada.',
  },
  {
    q: 'How much do managed IT services cost?',
    a: 'Managed IT is usually priced per user or per device per month, depending on the level of support and security you need. We give you a fixed, written quote after a free assessment so there are no surprises.',
  },
  {
    q: 'Are you only for large companies?',
    a: 'No. Most of our clients are small and mid-sized businesses with 5 to 150 employees that need enterprise-grade IT without hiring a full internal IT department. We also support companies that already have IT staff (co-managed IT).',
  },
  {
    q: 'How quickly do you respond to support requests?',
    a: 'Urgent issues are picked up right away during business hours, and our monitoring alerts us to many problems before you notice them. Response times are set out in writing in your service agreement.',
  },
  {
    q: 'Why choose a Canadian IT provider?',
    a: 'A Canadian provider understands Canadian privacy law (PIPEDA and provincial laws such as Alberta PIPA), can keep your data in Canadian data centres, works in your time zone, and can send a technician on site when needed.',
  },
  {
    q: 'Is there a long-term contract?',
    a: 'We earn your business every month. Ask about our flexible agreements during your free consultation.',
  },
];
