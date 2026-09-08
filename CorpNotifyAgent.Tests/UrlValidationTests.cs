namespace CorpNotifyAgent.Tests;

using Xunit;

public sealed class UrlValidationTests
{
    [Theory]
    [InlineData("https://intranet.example.test/news/1")]
    [InlineData("http://localhost:8000/news/1")]
    public void AcceptsHttpAndHttps(string url) => Assert.True(NotificationForm.IsSafeWebUrl(url));

    [Theory]
    [InlineData("file:///C:/Windows/System32/calc.exe")]
    [InlineData("javascript:alert(1)")]
    [InlineData("cmd://whoami")]
    [InlineData("powershell:whoami")]
    [InlineData("")]
    public void RejectsUnsafeSchemes(string url) => Assert.False(NotificationForm.IsSafeWebUrl(url));
}
