import os
import requests
from datetime import datetime, timedelta

# Configuración
USERNAME = "miguel-cordova7"
TOKEN = os.getenv("GH_TOKEN")
HEADERS = {"Authorization": f"Bearer {TOKEN}"}

def get_contributions():
    query = """
    query($userName:String!) {
      user(login: $userName){
        contributionsCollection {
          contributionCalendar {
            totalContributions
            weeks {
              contributionDays {
                contributionCount
                date
              }
            }
          }
        }
      }
    }
    """
    variables = {"userName": USERNAME}
    response = requests.post(
        "https://api.github.com/graphql",
        json={"query": query, "variables": variables},
        headers=HEADERS
    )
    if response.status_code != 200:
        raise Exception(f"Error en la API: {response.status_code}")
    return response.json()

def calculate_streaks(data):
    calendar = data['data']['user']['contributionsCollection']['contributionCalendar']
    total = calendar['totalContributions']

    days = []
    for week in calendar['weeks']:
        for day in week['contributionDays']:
            day['date_obj'] = datetime.strptime(day['date'], "%Y-%m-%d").date()
            days.append(day)

    days.sort(key=lambda x: x['date_obj'])

    current_streak = 0
    current_streak_date = None
    longest_streak = 0
    longest_streak_start = None
    longest_streak_end = None
    temp_streak = 0
    temp_streak_start = None

    today = datetime.now().date()
    yesterday = today - timedelta(days=1)

    for day in days:
        if day['contributionCount'] > 0:
            if temp_streak == 0:
                temp_streak_start = day['date_obj']
            temp_streak += 1
            if temp_streak > longest_streak:
                longest_streak = temp_streak
                longest_streak_start = temp_streak_start
                longest_streak_end = day['date_obj']
        else:
            temp_streak = 0
            temp_streak_start = None

    days_reversed = list(reversed(days))
    for i, day in enumerate(days_reversed):
        if i == 0 and day['contributionCount'] == 0:
            continue
        if day['contributionCount'] > 0:
            if current_streak == 0:
                current_streak_date = day['date_obj']
            current_streak += 1
        else:
            break

    calendar_start_date = days[0]['date_obj']
    current_date_formatted = datetime.now().strftime("%b %d") if current_streak_date else ""
    if current_streak == 0:
        last_commit_day = next((d for d in days_reversed if d['contributionCount'] > 0), None)
        if last_commit_day:
             current_date_formatted = last_commit_day['date_obj'].strftime("%b %d")
        else:
             current_date_formatted = yesterday.strftime("%b %d")

    def format_date_range(start, end):
        if not start or not end:
            return ""
        if start.year == end.year:
             return f"{start.strftime('%b %d')} - {end.strftime('%b %d')}"
        else:
             return f"{start.strftime('%b %d, %Y')} - {end.strftime('%b %d, %Y')}"

    total_date_range = f"{calendar_start_date.strftime('%b %d, %Y')} - Present"
    longest_streak_range = format_date_range(longest_streak_start, longest_streak_end)

    return total, current_streak, longest_streak, total_date_range, current_date_formatted, longest_streak_range

def generate_svg(total, current, longest, total_range, current_date, longest_range):

    svg_template = f"""<svg xmlns="http://www.w3.org/2000/svg" width="495" height="195">
        <style>
            .stat {{ font: 700 28px 'Segoe UI', Ubuntu, sans-serif; }}
            .label {{ font: 400 14px 'Segoe UI', Ubuntu, sans-serif; fill: #A3B3BC; }}
            .label-current {{ font: 700 14px 'Segoe UI', Ubuntu, sans-serif; fill: #28a745; }}
            .date {{ font: 400 12px 'Segoe UI', Ubuntu, sans-serif; fill: #768390; }}
        </style>
        
        <rect width="495" height="195" fill="#0d1117" rx="4.5" />

        <line x1="165" y1="35" x2="165" y2="160" stroke="#E4E2E2" stroke-opacity="0.2" stroke-width="1.5" />
        <line x1="330" y1="35" x2="330" y2="160" stroke="#E4E2E2" stroke-opacity="0.2" stroke-width="1.5" />

        <text x="82.5" y="90" fill="#00a8f3" class="stat" text-anchor="middle">{total}</text>
        <text x="82.5" y="125" class="label" text-anchor="middle" fill="#fff">Total Contributions</text>
        <text x="82.5" y="150" class="date" text-anchor="middle">{total_range}</text>

        <path d="M247.5 57A38 38 0 1 1 209.5 95A38 38 0 0 1 247.5 57M247.5 67A28 28 0 1 0 275.5 95A28 28 0 0 0 247.5 67" fill="#00a8f3" fill-rule="evenodd"/>

        <g transform="translate(240, 27)">
            <path fill="#00a8f3" d="M7.49 1.152L7.545 1.127l.035.045c2.422 3.193 4.414 5.346 4.414 8.01 0 3.328-2.585 5.818-5.994 5.818-3.409 0-5.994-2.49-5.994-5.818 0-2.31 1.488-4.576 3.51-6.686l.063-.065.046.06c1.17 1.517 2.016 3.064 2.016 4.706 0 1.25-.563 2.158-1.576 2.607a.75.75 0 0 0 .584 1.378c1.68-.713 2.492-2.302 2.492-3.985 0-2.315-1.196-4.22-2.64-6.05Zm0 .01-.002-.003.002.003Zm-.52.28-.052-.066-.022.03c-2.023 2.115-3.396 4.225-3.396 6.37 0 2.49 1.936 4.318 4.494 4.318 2.558 0 4.494-1.828 4.494-4.318 0-2.274-1.745-4.14-3.858-7.076-.84 1.636-1.594 3.238-1.594 5.031 0 1.62.775 2.91 2.036 3.58a.75.75 0 0 1-.708 1.32c-1.765-.945-2.828-2.695-2.828-4.9 0-2.053 1.055-3.83 2.434-5.289Z"></path>
        </g>
        
        <text x="247.5" y="105" fill="#28a745" class="stat" text-anchor="middle">{current}</text>
        
        <text x="247.5" y="145" class="label-current" text-anchor="middle">Current Streak</text>
        <text x="247.5" y="165" class="date" text-anchor="middle">{current_date}</text>

        <text x="412.5" y="90" fill="#00a8f3" class="stat" text-anchor="middle">{longest}</text>
        <text x="412.5" y="125" class="label" text-anchor="middle" fill="#fff">Longest Streak</text>
        <text x="412.5" y="150" class="date" text-anchor="middle">{longest_range}</text>
    </svg>"""
    
    with open("racha.svg", "w", encoding="utf-8") as file:
        file.write(svg_template)

if __name__ == "__main__":
    if not TOKEN:
        print("Error: No se encontró el token GH_TOKEN.")
    else:
        try:
             data = get_contributions()
             total, current, longest, total_range, current_date, longest_range = calculate_streaks(data)
             generate_svg(total, current, longest, total_range, current_date, longest_range)
             print("¡Archivo racha.svg generado con éxito!")
        except Exception as e:
             print(f"Ocurrió un error al generar el SVG: {e}")
