import { Button } from "@mui/material";
import { lazy, Suspense, useEffect, useState } from "@wordpress/element";
import Progress from "./components/Progress";
import "./styles/style.scss";
import { apiClient, getAppBaseUrl } from "./utils";

// Lazy load the larger components
const Dashboard = lazy(() => import("./dashboard/Dashboard"));
const Login = lazy(() => import("./login/Login"));

function App() {
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [loginOpen, setLoginOpen] = useState(false);

  useEffect(() => {
    setIsLoading(true);
    apiClient("login-token", "GET")
      .then((data) => {
        if (!data.loginToken) {
          throw new Error("No login token found");
        }
        setIsLoggedIn(true);
      })
      .catch((error) => {
        console.error("Failed to fetch login token:", error);
      })
      .finally(() => {
        setIsLoading(false);
      });
  }, []);

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
