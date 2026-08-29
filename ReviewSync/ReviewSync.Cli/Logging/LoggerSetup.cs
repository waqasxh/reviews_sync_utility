using Microsoft.Extensions.Configuration;
using Serilog;

namespace ReviewSync.Cli.Logging;

public static class LoggerSetup
{
    public static ILogger Create(IConfiguration configuration)
    {
        var logFilePath = configuration["Logging:LogFilePath"] ?? "logs/reviewsync-.log";

        return new LoggerConfiguration()
            .MinimumLevel.Information()
            .WriteTo.Console()
            .WriteTo.File(logFilePath, rollingInterval: RollingInterval.Day, retainedFileCountLimit: 30)
            .CreateLogger();
    }
}
