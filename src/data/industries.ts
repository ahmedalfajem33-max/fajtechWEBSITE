export type Industry = {
  slug: string;
  title: string;
  short: string;
  icon: string;
  intro: string;
  challenges: string[];
  solutions: { title: string; text: string }[];
  services: string[]; // service slugs
};

export const industries: Industry[] = [
  {
    slug: 'healthcare',
    title: 'Healthcare & Medical Practices',
    short: 'Secure, compliant IT that supports patient care, clinic operations and health-information privacy.',
    icon: 'heart',
    intro:
      'Clinics, dental offices, physiotherapy and specialist practices depend on their EMR, imaging and scheduling systems every minute they are open. We keep those systems running, protect patient information and help you meet your privacy obligations under Alberta’s Health Information Act and PIPEDA.',
    challenges: [
      'Downtime that cancels appointments and frustrates patients',
      'Protecting patient records from ransomware and data breaches',
      'Meeting health-privacy rules and audit requirements',
      'Supporting EMR, imaging, phones and front-desk systems from many vendors',
    ],
    solutions: [
      { title: 'EMR & clinical system support', text: 'We work with your EMR, imaging and practice-management vendors so problems get fixed — not bounced between support lines.' },
      { title: 'Patient-data protection', text: 'Encryption, MFA, secure email and 24/7 monitoring to keep health information private.' },
      { title: 'Backup you can rely on', text: 'Tested, immutable backups stored in Canada so a failure or ransomware attack does not stop patient care.' },
      { title: 'Privacy & compliance support', text: 'Access controls, audit logs and documentation that support your privacy impact assessments.' },
    ],
    services: ['managed-it', 'cybersecurity', 'backup-disaster-recovery'],
  },
  {
    slug: 'financial-services',
    title: 'Financial Services & Accounting',
    short: 'Robust infrastructure and cybersecurity for firms that handle sensitive client financial data.',
    icon: 'chart',
    intro:
      'Accounting firms, financial advisors, insurance brokers and lenders are prime targets for fraud and cyberattacks. We secure your systems, keep tax-season workloads running smoothly and help you show clients and regulators that their data is protected.',
    challenges: [
      'Phishing, wire fraud and business email compromise',
      'Peak-season pressure when systems cannot go down',
      'Client expectations and regulator questions about data security',
      'Secure remote access for advisors and staff working from anywhere',
    ],
    solutions: [
      { title: 'Email & fraud protection', text: 'Advanced filtering, impersonation detection and staff training to stop wire-fraud scams.' },
      { title: 'Secure remote work', text: 'MFA, managed devices and secure access to client files from the office, home or on the road.' },
      { title: 'Always-on systems', text: '24/7 monitoring and support so tax season and month-end are never derailed by IT problems.' },
      { title: 'Security documentation', text: 'Policies and evidence of controls to answer client, insurer and regulator questionnaires.' },
    ],
    services: ['cybersecurity', 'managed-it', 'cloud-services'],
  },
  {
    slug: 'legal',
    title: 'Legal',
    short: 'Data security, compliance and workflow optimisation for law firms.',
    icon: 'scale',
    intro:
      'Law firms hold some of the most sensitive information there is. We protect privileged client data, keep document and practice-management systems fast and reliable, and help you meet your professional obligations for confidentiality and record keeping.',
    challenges: [
      'Protecting privileged and confidential client information',
      'Keeping document management and practice-management software running',
      'Secure access for lawyers in court, at home or travelling',
      'Retaining records safely for years',
    ],
    solutions: [
      { title: 'Confidentiality by design', text: 'Encryption, access controls and secure file sharing for client matters.' },
      { title: 'Practice software support', text: 'We support your document management, billing and practice-management systems and work with their vendors.' },
      { title: 'Secure mobility', text: 'Managed laptops and phones with MFA so lawyers can work securely anywhere.' },
      { title: 'Long-term retention', text: 'Reliable backups and archiving that keep records available and protected.' },
    ],
    services: ['cybersecurity', 'managed-it', 'backup-disaster-recovery'],
  },
  {
    slug: 'construction',
    title: 'Construction & Trades',
    short: 'Reliable connectivity, mobile devices and cloud tools for teams on job sites and in the office.',
    icon: 'hardhat',
    intro:
      'Your people work from trucks, trailers and job sites as much as the office. We give them reliable devices, connectivity and cloud tools, and keep project files, estimating software and accounting systems secure and available.',
    challenges: [
      'Crews and site supervisors who need access to plans and files anywhere',
      'Temporary site offices that need internet and Wi-Fi fast',
      'Lost, broken or unmanaged phones and tablets',
      'Large drawings and project files that are slow to share',
    ],
    solutions: [
      { title: 'Job-site connectivity', text: 'Fast setup of internet, Wi-Fi and secure access for site trailers and temporary offices.' },
      { title: 'Managed mobile devices', text: 'Phones and tablets set up, secured and remotely wiped if lost.' },
      { title: 'Cloud file access', text: 'Microsoft 365 and cloud storage so plans and documents are available on any device.' },
      { title: 'Estimating & accounting support', text: 'We keep your industry software, licences and servers running smoothly.' },
    ],
    services: ['managed-it', 'cloud-services', 'network-security'],
  },
  {
    slug: 'energy',
    title: 'Oil, Gas & Energy',
    short: 'Mission-critical infrastructure built for safety, compliance and operational efficiency.',
    icon: 'flame',
    intro:
      'Energy and oilfield-services companies run operations where downtime is expensive and security is critical. We support field and office teams, secure networks that connect remote sites, and help protect the systems your operations depend on.',
    challenges: [
      'Remote sites and field staff with limited connectivity',
      'Rising cyber threats against energy companies',
      'Strict safety, compliance and audit requirements',
      'Operations that run around the clock',
    ],
    solutions: [
      { title: '24/7 support', text: 'Our team is available day and night, matching your operations schedule.' },
      { title: 'Remote site networking', text: 'Secure connections between head office, yards and field locations.' },
      { title: 'Layered security', text: 'Monitoring, network segmentation and incident response to protect critical systems.' },
      { title: 'Compliance documentation', text: 'Controls and records that support audits and client security requirements.' },
    ],
    services: ['network-security', 'cybersecurity', 'managed-it'],
  },
  {
    slug: 'manufacturing',
    title: 'Manufacturing & Logistics',
    short: 'Automation, IoT integration and dependable IT for production environments.',
    icon: 'factory',
    intro:
      'When systems stop on the shop floor or in the warehouse, so does revenue. We keep ERP, inventory, shipping and production systems available, secure connected equipment, and automate manual processes between them.',
    challenges: [
      'Production or shipping stopped by IT failures',
      'Connected machines and IoT devices that are hard to secure',
      'Manual data entry between ERP, inventory and shipping systems',
      'Multiple shifts that need support outside office hours',
    ],
    solutions: [
      { title: 'Uptime for operations', text: '24/7 monitoring and support across every shift.' },
      { title: 'Secure OT & IoT', text: 'Segmented networks that keep machines and office systems safely separated.' },
      { title: 'Process automation', text: 'Integrations that move data between ERP, inventory and shipping automatically.' },
      { title: 'Reliable infrastructure', text: 'Servers, Wi-Fi and networking built for warehouses and plant floors.' },
    ],
    services: ['managed-it', 'automation', 'network-security'],
  },
  {
    slug: 'retail',
    title: 'Retail & eCommerce',
    short: 'Point-of-sale support, cloud systems and secure networks for retail operations.',
    icon: 'cart',
    intro:
      'From single storefronts to multi-location retailers and online stores, we keep point-of-sale systems, payments and inventory running, and protect customer and card data.',
    challenges: [
      'POS or payment outages during busy hours',
      'Protecting customer and payment-card data',
      'Managing technology across multiple locations',
      'Keeping online stores, inventory and accounting in sync',
    ],
    solutions: [
      { title: 'POS & payment support', text: 'Fast help for point-of-sale, payment terminals and store networks — including evenings and weekends.' },
      { title: 'Secure store networks', text: 'Separated networks for payments, staff and guest Wi-Fi to support PCI requirements.' },
      { title: 'Multi-location management', text: 'One partner for every store, with standard setups that are easy to support.' },
      { title: 'Systems integration', text: 'Automations that connect your online store, inventory and accounting.' },
    ],
    services: ['managed-it', 'network-security', 'automation'],
  },
  {
    slug: 'real-estate',
    title: 'Real Estate & Property Management',
    short: 'Flexible, secure IT that supports collaboration, data protection and growth.',
    icon: 'building',
    intro:
      'Brokerages and property managers handle personal and financial information for clients, tenants and owners, often from phones and the road. We give your team secure, mobile-friendly technology and protect the data you are trusted with.',
    challenges: [
      'Agents and managers working mostly from mobile devices',
      'Wire-fraud and phishing scams targeting transactions',
      'Tenant and client personal information to protect',
      'Systems spread across several offices or properties',
    ],
    solutions: [
      { title: 'Mobile-first security', text: 'Managed phones and laptops with MFA and secure email.' },
      { title: 'Fraud prevention', text: 'Email protection and staff training focused on transaction scams.' },
      { title: 'Cloud collaboration', text: 'Microsoft 365 and secure file sharing for teams on the move.' },
      { title: 'Multi-site networks', text: 'Reliable internet, Wi-Fi and cameras across offices and buildings.' },
    ],
    services: ['cybersecurity', 'cloud-services', 'managed-it'],
  },
  {
    slug: 'non-profits',
    title: 'Non-Profits',
    short: 'Cost-effective IT that helps non-profits focus resources on impact, not infrastructure.',
    icon: 'hand',
    intro:
      'Non-profits need dependable, secure technology on a tight budget. We help you make the most of non-profit software programs and licensing, protect donor and client information, and keep staff and volunteers productive.',
    challenges: [
      'Limited budget and no in-house IT staff',
      'Protecting donor, client and volunteer information',
      'Volunteers and staff coming and going',
      'Aging equipment and mixed software',
    ],
    solutions: [
      { title: 'Budget-friendly plans', text: 'Predictable pricing and help using non-profit licensing programs.' },
      { title: 'Simple onboarding', text: 'Fast, secure setup and removal of accounts for staff and volunteers.' },
      { title: 'Data protection', text: 'Security and backups that protect the people you serve.' },
      { title: 'Technology planning', text: 'A practical roadmap and budget for replacing equipment over time.' },
    ],
    services: ['managed-it', 'it-consulting-vcio', 'cloud-services'],
  },
  {
    slug: 'education',
    title: 'Education & Training',
    short: 'Reliable IT for schools, institutions, online learning and student management.',
    icon: 'book',
    intro:
      'Private schools, colleges and training providers rely on technology for classrooms, online learning and student records. We keep it reliable and secure, and make devices and accounts easy to manage at scale.',
    challenges: [
      'Hundreds of student and staff devices and accounts',
      'Classroom technology that must work when class starts',
      'Protecting student records and personal information',
      'Secure Wi-Fi for students, staff and guests',
    ],
    solutions: [
      { title: 'Device & account management', text: 'Centralised setup and control of laptops, tablets and user accounts.' },
      { title: 'Classroom support', text: 'Fast help for projectors, displays and learning platforms.' },
      { title: 'Student data protection', text: 'Access controls, filtering and backups for student information.' },
      { title: 'Campus networks', text: 'Segmented, high-capacity Wi-Fi for every building.' },
    ],
    services: ['managed-it', 'network-security', 'cloud-services'],
  },
  {
    slug: 'government',
    title: 'Local Government',
    short: 'Secure, scalable systems that support public services and community operations.',
    icon: 'landmark',
    intro:
      'Municipalities and public agencies deliver essential services with limited IT resources. We support your staff, secure public data and help plan technology investments that serve the community.',
    challenges: [
      'Small IT teams supporting many departments',
      'Ransomware attacks targeting public bodies',
      'Freedom-of-information and privacy obligations',
      'Long-term budgeting for technology',
    ],
    solutions: [
      { title: 'Co-managed IT', text: 'Extra hands, tools and 24/7 coverage alongside your internal staff.' },
      { title: 'Ransomware defence', text: 'Layered security, monitoring and tested recovery plans.' },
      { title: 'Records & privacy', text: 'Secure storage, retention and access controls for public records.' },
      { title: 'Technology planning', text: 'Multi-year roadmaps and budgets aligned with council priorities.' },
    ],
    services: ['managed-it', 'cybersecurity', 'it-consulting-vcio'],
  },
  {
    slug: 'automotive',
    title: 'Automotive & Dealerships',
    short: 'Technology that keeps dealerships, service centres and operations running smoothly.',
    icon: 'car',
    intro:
      'Dealerships and service centres depend on their dealer management system, diagnostic tools and phones every hour they are open. We keep it all running and protect the customer and financing information you handle.',
    challenges: [
      'Dealer management system downtime that stops sales and service',
      'Protecting customer financing and personal information',
      'Diagnostic equipment and shop-floor networks',
      'Phones and lead systems that must never miss a customer',
    ],
    solutions: [
      { title: 'DMS & vendor support', text: 'We work with your DMS and OEM vendors to resolve issues fast.' },
      { title: 'Customer-data security', text: 'Protection for financing documents and personal information.' },
      { title: 'Shop networks', text: 'Reliable, separated networks for service bays and diagnostic tools.' },
      { title: 'Extended-hours support', text: '24/7 support that covers evening and weekend sales hours.' },
    ],
    services: ['managed-it', 'cybersecurity', 'network-security'],
  },
];

export const locations = [
  {
    slug: 'calgary',
    name: 'Calgary',
    title: 'IT Support & Cybersecurity in Calgary',
    intro:
      'FAJTech is a Calgary-based IT company. We provide managed IT, cybersecurity and cloud services to businesses across the city — from downtown offices to the industrial parks in the northeast and southeast — with 24/7/365 remote support and local technicians for on-site work.',
    onsite: 'On-site support across all Calgary quadrants.',
    nearby: ['Airdrie', 'Cochrane', 'Chestermere', 'Okotoks'],
  },
  {
    slug: 'airdrie',
    name: 'Airdrie',
    title: 'IT Support in Airdrie',
    intro:
      'Airdrie businesses get the same 24/7 support, cybersecurity and cloud services as our Calgary clients. Most issues are fixed remotely within minutes, and our technicians are a short drive away when you need someone on site.',
    onsite: 'On-site visits from our Calgary team, just south on Highway 2.',
    nearby: ['Calgary', 'Cochrane', 'Chestermere'],
  },
  {
    slug: 'cochrane',
    name: 'Cochrane',
    title: 'IT Support in Cochrane',
    intro:
      'From retail on First Street to professional offices and trades businesses, we help Cochrane companies stay secure and productive with managed IT, Microsoft 365 and 24/7 remote support.',
    onsite: 'Scheduled and urgent on-site visits from Calgary.',
    nearby: ['Calgary', 'Airdrie', 'Okotoks'],
  },
  {
    slug: 'okotoks',
    name: 'Okotoks',
    title: 'IT Support in Okotoks',
    intro:
      'Okotoks businesses rely on FAJTech for managed IT, backup and cybersecurity — with help available around the clock and technicians nearby when something needs hands-on attention.',
    onsite: 'On-site visits from our Calgary team.',
    nearby: ['Calgary', 'Cochrane', 'Chestermere'],
  },
  {
    slug: 'chestermere',
    name: 'Chestermere',
    title: 'IT Support in Chestermere',
    intro:
      'Chestermere companies get enterprise-grade IT support and security without hiring an internal IT team: 24/7 help desk, proactive monitoring and on-site visits when needed.',
    onsite: 'On-site visits from our Calgary team, just east of the city.',
    nearby: ['Calgary', 'Airdrie', 'Okotoks'],
  },
  {
    slug: 'canada',
    name: 'Canada',
    title: 'Remote IT Support Across Canada',
    intro:
      'Most IT support today is delivered remotely — so wherever your business is in Canada, FAJTech can be your IT department. We provide 24/7/365 help desk, cybersecurity, Microsoft 365 and cloud services to businesses coast to coast, with Canadian data residency and privacy built in.',
    onsite: 'Remote support nationwide; on-site visits arranged for projects and installations.',
    nearby: ['Calgary'],
  },
];
