import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
import { z } from 'astro/zod';

const resources = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './src/content/resources' }),
  schema: z.object({
    title: z.string(),
    description: z.string(),
    date: z.coerce.date(),
    updated: z.coerce.date().optional(),
    category: z.enum(['Cybersecurity', 'Managed IT', 'Compliance', 'Business']),
    readingMinutes: z.number().optional(),
    relatedService: z.string().optional(), // service slug
  }),
});

export const collections = { resources };
