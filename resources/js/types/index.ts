export type * from './auth';
export type * from './navigation';
export type * from './ui';

/**
 * Base for page component props. Shared props (auth, currentSpace,
 * flash…) are typed globally via InertiaConfig in global.d.ts; pages
 * intersect this with their own props.
 */
export type PageProps = Record<string, unknown>;
