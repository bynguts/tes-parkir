import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import path from "node:path";

export default defineConfig({
  plugins: [react()],
  base: "./",
  build: {
    outDir: path.resolve(__dirname, "../assets/home"),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, "src/main.tsx"),
        revenueWidget: path.resolve(__dirname, "src/revenue-widget.tsx"),
        todayRevenueWidget: path.resolve(__dirname, "src/today-revenue-widget.tsx"),
      },
    },
  },
});

