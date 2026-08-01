import { Button } from "@mui/material";
import { lazy, Suspense, useCallback, useEffect, useState } from "@wordpress/element";
import Progress from "./components/Progress";
import "./styles/style.scss";
import { getAppBaseUrl } from "./utils";
import { checkConnectionState } from "./utils/check-connection-state";
import { clearWpTodoDismissed } from "./utils/wp-todo-dismiss";

// Lazy load the larger components
const Dashboard = lazy(() => import("./dashboard/Dashboard"));
const Login = lazy(() => import("./login/Login"));

function App() {
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [loginOpen, setLoginOpen] = useState(false);

  const refreshConnectionState = useCallback(async () => {
    setIsLoading(true);
    try {
      const connected = await checkConnectionState();
      setIsLoggedIn(connected);
      if (!connected) {
        clearWpTodoDismissed();
      }
    } catch (error) {
      console.error("Failed to verify Adflipr connection:", error);
      setIsLoggedIn(false);
      clearWpTodoDismissed();
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    refreshConnectionState();
  }, [refreshConnectionState]);

  useEffect(() => {
    const onVisibilityChange = () => {
      if (document.visibilityState === "visible") {
        refreshConnectionState();
      }
    };
    document.addEventListener("visibilitychange", onVisibilityChange);
    return () => {
      document.removeEventListener("visibilitychange", onVisibilityChange);
    };
  }, [refreshConnectionState]);

  return (
    <div className="adflipr-app-container">
      <div className="adflipr-app-header">
        <span>Adflipr Overview</span>
        <Button
          type="button"
          variant="contained"
          color="primary"
          className="btn btn-primary adflipr-header-action"
          disableRipple
          disableElevation
          onClick={() => {
            if (isLoggedIn) {
              window.open(`${getAppBaseUrl()}/app`, "_blank");
            } else {
              setLoginOpen(true);
            }
          }}
        >
          <span>{isLoggedIn ? "View App" : "Login with Adflipr"}</span>
        </Button>
      </div>
      {isLoading ? (
        <Progress />
      ) : (
        <Suspense fallback={<Progress />}>
          {isLoggedIn ? (
            <Dashboard />
          ) : (
            <Login
              setIsLoggedIn={setIsLoggedIn}
              loginOpen={loginOpen}
              onLoginOpen={() => setLoginOpen(true)}
              onLoginClose={() => setLoginOpen(false)}
            />
          )}
        </Suspense>
      )}
    </div>
  );
}

export default App;
