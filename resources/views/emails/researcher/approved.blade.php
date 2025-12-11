@component('mail::message')
# Congratulations! Your Researcher Account is Approved 🎉

Dear **{{ $user->firstname }} {{ $user->lastname }}**,

We are excited to inform you that your researcher profile has been **approved** by our admin team!

You now have full access to all researcher features including:
- Conducting field studies
- Accessing farmer & vet data (with permission)
- Publishing research findings
- Managing research projects

@component('mail::button', ['url' => config('app.frontend_url') . '/login'])
Login to Dashboard
@endcomponent

Thank you for joining our research community!  
The FarmManager Research Team

<small>If you didn’t apply to become a researcher, please contact support immediately.</small>
@endcomponent