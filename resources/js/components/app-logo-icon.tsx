import type { SVGAttributes } from 'react';

/**
 * The app icon (public/resume-rover-icon.svg): a résumé inside a match-score
 * ring, on the brand's navy tile. Full color on purpose, so it looks the same
 * in light and dark mode.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 100 100"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden
            {...props}
        >
            <rect width="100" height="100" rx="23" fill="#0F1B2D" />
            <g transform="translate(15 15) scale(0.7)">
                <circle
                    cx="50"
                    cy="50"
                    r="38"
                    fill="none"
                    stroke="#2A3A52"
                    strokeWidth="9"
                />
                <circle
                    cx="50"
                    cy="50"
                    r="38"
                    fill="none"
                    stroke="#0E9F6E"
                    strokeWidth="9"
                    strokeLinecap="round"
                    strokeDasharray="186 239"
                    transform="rotate(-90 50 50)"
                />
                <rect x="37" y="29" width="26" height="34" rx="4" fill="#fff" />
                <rect
                    x="42"
                    y="36"
                    width="16"
                    height="3.5"
                    rx="1.75"
                    fill="#0F1B2D"
                />
                <rect
                    x="42"
                    y="43"
                    width="11"
                    height="3.5"
                    rx="1.75"
                    fill="#0F1B2D"
                />
                <rect
                    x="42"
                    y="50"
                    width="14"
                    height="3.5"
                    rx="1.75"
                    fill="#0F1B2D"
                />
                <circle
                    cx="63"
                    cy="64"
                    r="10"
                    fill="#0E9F6E"
                    stroke="#0F1B2D"
                    strokeWidth="3.5"
                />
                <path
                    d="M58.2 64.2 L61.6 67.4 L67.6 60.8"
                    fill="none"
                    stroke="#fff"
                    strokeWidth="3"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </g>
        </svg>
    );
}
