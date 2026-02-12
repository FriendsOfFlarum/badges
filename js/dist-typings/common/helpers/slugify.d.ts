/**
 * Convert a string to a URL-friendly slug.
 * - Converts to lowercase
 * - Replaces non-alphanumeric characters with hyphens
 * - Removes leading/trailing hyphens
 *
 * @example slugify("Hello World!") // "hello-world"
 * @example slugify("100 Posts Master") // "100-posts-master"
 */
export default function slugify(text: string): string;
