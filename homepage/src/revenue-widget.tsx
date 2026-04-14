import React from "react";
import ReactDOM from "react-dom/client";
import { Player } from "@remotion/player";
import { RevenueGraph } from "./components/ui/revenue-graph";

function mount() {
  const el = document.getElementById("revenue-widget-root");
  if (!el) return;

  const kpiTarget = Number(el.getAttribute("data-kpi") || "0") || 0;
  const accentColor = el.getAttribute("data-accent") || "#22c55e";

  ReactDOM.createRoot(el).render(
    <div style={{ position: "absolute", inset: 0 }}>
      <Player
        component={RevenueGraph as any}
        inputProps={{ kpiTarget, accentColor, textElementId: "revenue-text" }}
        durationInFrames={150}
        fps={30}
        compositionWidth={300}
        compositionHeight={150}
        autoPlay
        loop={false}
        controls={false}
        clickToPlay={false}
        style={{
          width: "100%",
          height: "100%",
          opacity: 0.8,
          filter: "saturate(1.1) blur(0.2px)",
        }}
      />
      <div
        style={{
          position: "absolute",
          inset: 0,
          background:
            "radial-gradient(900px 400px at 70% 30%, rgba(34,197,94,0.22), transparent 55%), radial-gradient(700px 340px at 30% 70%, rgba(34,197,94,0.12), transparent 60%)",
          pointerEvents: "none",
        }}
      />
    </div>,
  );
}

mount();

