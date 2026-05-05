import React from "react";
import ReactDOM from "react-dom/client";
import { Player } from "@remotion/player";
import { RevenueGraph } from "./components/ui/revenue-graph";

function mount() {
  const el = document.getElementById("today-revenue-widget-root");
  if (!el) return;

  const kpiTarget = Number(el.getAttribute("data-kpi") || "0") || 0;

  ReactDOM.createRoot(el).render(
    <div style={{ position: "absolute", inset: 0 }}>
      <Player
        component={RevenueGraph as any}
        inputProps={{ kpiTarget, accentColor: "#06B6D4", textElementId: "today-revenue-text" }}
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
          filter: "saturate(1.15)",
        }}
      />
      <div
        style={{
          position: "absolute",
          inset: 0,
          background:
            "radial-gradient(600px 240px at 70% 25%, rgba(34,197,94,0.18), transparent 58%)",
          pointerEvents: "none",
        }}
      />
    </div>,
  );
}

mount();

