export default {
  async email(message, env, ctx) {
    const raw = await new Response(message.raw).text();

    const payload = {
      from: message.from,
      to: message.to,
      subject: message.headers.get('subject') || '(Tanpa Subjek)',
      message_id: message.headers.get('message-id') || ('cf-' + Date.now() + '@azkario.biz.id'),
      date: message.headers.get('date') || new Date().toISOString(),
      raw_mime: raw
    };

    const targetUrl = 'https://estrogen-stubbed-secret.ngrok-free.dev/api/mail/inbound?token=apex-inbound-secret';

    await fetch(targetUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    });
  }
};
