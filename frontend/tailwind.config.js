/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        border: "var(--border)",
        input: "var(--input)",
        ring: "var(--ring)",
        background: "var(--background)",
        foreground: "var(--foreground)",
        primary: {
          DEFAULT: "var(--primary)",
          foreground: "var(--primary-foreground)",
        },
        brand: {
          DEFAULT: "var(--brand)",
          subtle: "var(--brand-subtle)",
        },
        secondary: {
          DEFAULT: "var(--secondary)",
          foreground: "var(--secondary-foreground)",
        },
        destructive: {
          DEFAULT: "var(--destructive)",
          foreground: "var(--destructive-foreground)",
        },
        muted: {
          DEFAULT: "var(--muted)",
          foreground: "var(--muted-foreground)",
        },
        accent: {
          DEFAULT: "var(--accent)",
          foreground: "var(--accent-foreground)",
        },
        popover: {
          DEFAULT: "var(--popover)",
          foreground: "var(--popover-foreground)",
        },
        card: {
          DEFAULT: "var(--card)",
          foreground: "var(--card-foreground)",
        },
        main: "var(--main)",
        mtext: "var(--mtext)",
        bw: "var(--bw)",
        shadow: "var(--shadow)",
      },
      borderRadius: {
        base: "var(--radius)",
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
        sm: "calc(var(--radius) - 4px)",
      },
      boxShadow: {
        shadow: "var(--shadow-shadow)",
      },
      translate: {
        boxShadowX: "var(--spacing-boxShadowX)",
        boxShadowY: "var(--spacing-boxShadowY)",
        reverseBoxShadowX: "var(--spacing-reverseBoxShadowX)",
        reverseBoxShadowY: "var(--spacing-reverseBoxShadowY)",
      },
    },
  },
  plugins: [],
}
