import { motion, useReducedMotion } from "framer-motion";
import { GateHero } from "./components/GateHero";

declare global {
  interface Window {
    __HOME_NEXT__?: string;
    __HOME_IS_LOGGED_IN__?: boolean;
  }
}

function buildLoginHref(next?: string) {
  const n = (next || "").trim();
  const qs = n ? `&next=${encodeURIComponent(n)}` : "";
  return `login.php?from=home${qs}`;
}

export function App() {
  const reduce = useReducedMotion();
  const next = window.__HOME_NEXT__ || "";
  const isLoggedIn = Boolean(window.__HOME_IS_LOGGED_IN__);

  return (
    <div className="home-app">
      <div className="container py-5">
        <motion.div
          initial={reduce ? false : { opacity: 0, y: 16 }}
          animate={reduce ? undefined : { opacity: 1, y: 0 }}
          transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
          className="row g-4 align-items-center"
        >
          <div className="col-12 col-lg-6">
            <div className="home-eyebrow">Smart Gate • Slot Map • Revenue</div>
            <h1 className="home-title">
              Enterprise Parking
              <span className="home-title-accent"> Operations</span>, built for speed and
              control.
            </h1>
            <p className="home-subtitle">
              Monitor availability, automate entry/exit flows, and keep audit-ready logs
              with a modern glass UI that feels premium—without distracting operators.
            </p>

            <div className="d-flex flex-wrap gap-2 mt-4">
              {isLoggedIn ? (
                <a className="btn btn-success home-btn" href="index.php">
                  <i className="fas fa-tachometer-alt me-2" />
                  Go to Dashboard
                </a>
              ) : (
                <a className="btn btn-success home-btn" href={buildLoginHref(next)}>
                  <i className="fas fa-shield-alt me-2" />
                  Login Workspace
                </a>
              )}

              <a className="btn btn-outline-light home-btn home-btn-secondary" href="#features">
                <i className="fas fa-layer-group me-2" />
                Explore Features
              </a>
            </div>

            <div className="home-trust mt-4">
              <div className="trust-pill">
                <i className="fas fa-lock me-2" />
                Secure session & role-based access
              </div>
              <div className="trust-pill">
                <i className="fas fa-bolt me-2" />
                Operator-first workflows
              </div>
              <div className="trust-pill">
                <i className="fas fa-shield-halved me-2" />
                Audit-friendly logs
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-6">
            <GateHero />
          </div>
        </motion.div>

        <div id="features" className="mt-5 pt-4">
          <div className="home-section-title">Core Modules</div>
          <div className="row g-3 mt-1">
            <div className="col-12 col-md-6 col-xl-3">
              <a className="glass-card home-feature" href="modules/operations/gate_simulator.php">
                <div className="home-feature-icon text-success">
                  <i className="fas fa-door-open" />
                </div>
                <div className="home-feature-title">Smart Gate</div>
                <div className="home-feature-desc">Entry & exit simulator and scanning flow.</div>
              </a>
            </div>
            <div className="col-12 col-md-6 col-xl-3">
              <a className="glass-card home-feature" href="modules/reports/slot_map.php">
                <div className="home-feature-icon text-warning">
                  <i className="fas fa-map" />
                </div>
                <div className="home-feature-title">Slot Map</div>
                <div className="home-feature-desc">Real-time slot visualization by area.</div>
              </a>
            </div>
            <div className="col-12 col-md-6 col-xl-3">
              <a className="glass-card home-feature" href="modules/reports/revenue.php">
                <div className="home-feature-icon text-info">
                  <i className="fas fa-chart-line" />
                </div>
                <div className="home-feature-title">Revenue</div>
                <div className="home-feature-desc">Daily trends and finance aggregates.</div>
              </a>
            </div>
            <div className="col-12 col-md-6 col-xl-3">
              <a className="glass-card home-feature" href="modules/operations/reservation.php">
                <div className="home-feature-icon text-primary">
                  <i className="fas fa-calendar-check" />
                </div>
                <div className="home-feature-title">Reservations</div>
                <div className="home-feature-desc">Premium allocation and pre-booking.</div>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

