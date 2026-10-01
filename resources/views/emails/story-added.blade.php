<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $story->title }}</title>
    <style>
        .story-body p { margin: 0 0 16px; }
        .story-body h1, .story-body h2, .story-body h3 { margin: 24px 0 12px; line-height: 1.3; }
        .story-body ul, .story-body ol { margin: 0 0 16px; padding-left: 24px; }
        .story-body blockquote { margin: 0 0 16px; padding-left: 16px; border-left: 3px solid #e4e4e7; color: #52525b; }
        .story-body a { color: #2563eb; }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; border:1px solid #e4e4e7; border-radius:8px;">
                    <tr>
                        <td style="padding:24px 32px;">
                            <p style="margin:0 0 16px; font-size:15px; line-height:1.5;">
                                {{ $story->creator->name }} added a story: {{ $story->title }}.
                            </p>

                            <p style="margin:0 0 24px; font-size:15px; line-height:1.5;">
                                <a href="{{ $story->wikiShowUrl() }}" style="color:#2563eb; text-decoration:underline;">{{ $story->wikiShowUrl() }}</a>
                            </p>

                            <hr style="border:none; border-top:1px solid #e4e4e7; margin:0 0 24px;">

                            <div class="story-body" style="font-size:15px; line-height:1.6;">
                                {!! $bodyHtml !!}
                            </div>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                    <tr>
                        <td style="padding:16px 32px; font-size:12px; line-height:1.5; color:#71717a;">
                            You're getting this because new story emails are turned on in your profile settings.
                            Turn them off at {{ route('profile.edit') }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
