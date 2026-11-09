import type { SVGAttributes } from 'react';

/**
 * A compass needle in a ring: finding the right direction. Filled shapes only,
 * so callers can tint it with `fill-current`.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M20 2a18 18 0 1 0 0 36 18 18 0 0 0 0-36Zm0 4a14 14 0 1 0 0 28 14 14 0 0 0 0-28Z"
            />
            <path d="M28.5 11.5 22.4 22.4 11.5 28.5l6.1-10.9 10.9-6.1Z" />
            <circle cx="20" cy="20" r="2" opacity="0.35" />
        </svg>
    );
}
